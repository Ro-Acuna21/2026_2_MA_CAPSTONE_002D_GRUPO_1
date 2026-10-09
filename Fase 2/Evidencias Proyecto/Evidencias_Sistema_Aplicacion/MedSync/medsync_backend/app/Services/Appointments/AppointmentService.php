<?php

namespace App\Services\Appointments;

use App\Models\Center\Appointment;
use App\Models\Center\AppointmentHistory;
use App\Models\Center\Availability;
use App\Models\Center\Patient;
use App\Models\Center\Professional;
use App\Models\Center\Service;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Centraliza toda la lógica de negocio del flujo de reservas,
 * descrita en README_RESERVAS_DISPONIBILIDAD_BD.md.
 *
 * Todas las tablas involucradas (appointments, appointment_history,
 * availabilities, services, specialties, professionals) viven en la
 * conexión "center", por lo que aquí sí es seguro envolver la
 * creación/modificación de una reserva en una única transacción.
 */
class AppointmentService
{
    /**
     * Zona horaria utilizada para calcular la regla de las 24 horas.
     *
     * MedSync no persiste todavía una zona horaria por centro médico,
     * por lo que se deja fija a la del centro piloto (Chile) hasta que
     * se implemente la resolución dinámica de centros.
     */
    private const CENTER_TIMEZONE = 'America/Santiago';

    private const BLOCKING_STATUSES = ['PENDIENTE', 'CONFIRMADA'];

    /**
     * Prestaciones activas, con su especialidad, disponibles para reservar.
     */
    public function listActiveServices()
    {
        return Service::where('is_active', true)
            ->whereHas('specialty', fn ($query) => $query->where('is_active', true))
            ->with('specialty')
            ->orderBy('name')
            ->get();
    }

    /**
     * Profesionales activos que atienden la especialidad de la prestación.
     */
    public function listProfessionalsForService(Service $service)
    {
        return Professional::where('is_active', true)
            ->whereHas(
                'specialties',
                fn ($query) => $query->whereKey($service->specialty_id)
            )
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * Calcula los horarios reservables de un profesional para una
     * prestación en una fecha determinada, descontando los bloques
     * ya ocupados por otras reservas activas.
     *
     * @return array<int, string> horarios en formato "H:i"
     */
    public function getAvailableSlots(Professional $professional, Service $service, string $date): array
    {
        if (! $professional->is_active || ! $service->is_active) {
            return [];
        }

        $this->ensureProfessionalOffersService($professional, $service);

        $date = Carbon::parse($date)->toDateString();
        $weekday = Carbon::parse($date)->dayOfWeekIso;

        $availabilities = Availability::where('professional_id', $professional->id)
            ->where('weekday', $weekday)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        if ($availabilities->isEmpty()) {
            return [];
        }

        $busy = Appointment::where('professional_id', $professional->id)
            ->where('appointment_date', $date)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->get(['start_time', 'end_time']);

        $duration = $service->duration_minutes;
        $isToday = $date === Carbon::now(self::CENTER_TIMEZONE)->toDateString();
        $nowTime = Carbon::now(self::CENTER_TIMEZONE)->format('H:i:s');

        $slots = [];

        foreach ($availabilities as $availability) {
            $cursor = Carbon::createFromFormat('H:i:s', $this->normalizeTime($availability->start_time));
            $blockEnd = Carbon::createFromFormat('H:i:s', $this->normalizeTime($availability->end_time));

            while (true) {
                $slotStart = $cursor->format('H:i:s');
                $slotEndCarbon = $cursor->copy()->addMinutes($duration);
                $slotEnd = $slotEndCarbon->format('H:i:s');

                if ($slotEndCarbon->gt($blockEnd)) {
                    break;
                }

                $isPast = $isToday && $slotStart <= $nowTime;

                $overlaps = ! $isPast && $busy->contains(
                    fn ($appointment) => $appointment->start_time < $slotEnd
                        && $appointment->end_time > $slotStart
                );

                if (! $isPast && ! $overlaps) {
                    $slots[] = substr($slotStart, 0, 5);
                }

                $cursor->addMinutes($duration);
            }
        }

        return $slots;
    }

    /**
     * Crea una reserva validando nuevamente todas las reglas de negocio
     * (README_RESERVAS_DISPONIBILIDAD_BD.md, secciones 13-18 y 27).
     */
    public function createAppointment(Patient $patient, array $data, User $actor, string $source = 'WEB'): Appointment
    {
        $service = $this->findActiveService((int) $data['service_id']);
        $professional = $this->findActiveProfessional((int) $data['professional_id']);

        $this->ensureProfessionalOffersService($professional, $service);

        $date = $this->normalizeFutureDate($data['appointment_date']);
        $weekday = Carbon::parse($date)->dayOfWeekIso;

        $startTime = $this->normalizeTime($data['start_time']);
        $endTime = $this->addMinutes($startTime, $service->duration_minutes);

        $this->ensureWithinAvailability($professional->id, $weekday, $startTime, $endTime);

        return DB::connection('center')->transaction(function () use ($patient, $professional, $service, $date, $startTime, $endTime, $actor, $source) {
            // Laravel vuelve a validar el solapamiento inmediatamente
            // antes del INSERT, ya dentro de la transacción, porque dos
            // pacientes pueden haber visto la misma hora disponible.
            $this->ensureNoOverlap($professional->id, $date, $startTime, $endTime);

            $appointment = Appointment::create([
                'patient_id' => $patient->id,
                'professional_id' => $professional->id,
                'service_id' => $service->id,
                'appointment_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => 'PENDIENTE',
                'source' => $source,
                'overbook' => false,
                'created_by' => $actor->id,
            ]);

            AppointmentHistory::create([
                'appointment_id' => $appointment->id,
                'actor_user_id' => $actor->id,
                'event_type' => 'CREACION',
                'new_status' => 'PENDIENTE',
                'new_date' => $date,
                'new_start_time' => $startTime,
                'new_end_time' => $endTime,
                'new_professional_id' => $professional->id,
                'new_service_id' => $service->id,
            ]);

            return $appointment;
        });
    }

    /**
     * Reprograma una reserva existente del paciente autenticado.
     */
    public function rescheduleAppointment(Appointment $appointment, Patient $patient, array $data, User $actor): Appointment
    {
        $this->ensureOwnership($appointment, $patient);
        $this->ensureModifiable($appointment, 'reprogramar');
        $this->ensureWithinChangeWindow($appointment, 'reprogramar');

        $service = $this->findActiveService((int) ($data['service_id'] ?? $appointment->service_id));
        $professional = $this->findActiveProfessional((int) ($data['professional_id'] ?? $appointment->professional_id));

        $this->ensureProfessionalOffersService($professional, $service);

        $date = $this->normalizeFutureDate($data['appointment_date']);
        $weekday = Carbon::parse($date)->dayOfWeekIso;

        $startTime = $this->normalizeTime($data['start_time']);
        $endTime = $this->addMinutes($startTime, $service->duration_minutes);

        $this->ensureWithinAvailability($professional->id, $weekday, $startTime, $endTime);

        return DB::connection('center')->transaction(function () use ($appointment, $patient, $professional, $service, $date, $startTime, $endTime, $actor) {
            $lockedAppointment = Appointment::query()
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureOwnership($lockedAppointment, $patient);
            $this->ensureModifiable($lockedAppointment, 'reprogramar');
            $this->ensureWithinChangeWindow($lockedAppointment, 'reprogramar');
            $this->ensureNoOverlap($professional->id, $date, $startTime, $endTime, $lockedAppointment->id);

            $oldDate = $lockedAppointment->appointment_date->toDateString();
            $oldStartTime = $lockedAppointment->start_time;
            $oldEndTime = $lockedAppointment->end_time;
            $oldProfessionalId = $lockedAppointment->professional_id;
            $oldServiceId = $lockedAppointment->service_id;

            $lockedAppointment->update([
                'professional_id' => $professional->id,
                'service_id' => $service->id,
                'appointment_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]);

            $historyData = [
                'appointment_id' => $lockedAppointment->id,
                'actor_user_id' => $actor->id,
                'event_type' => 'REPROGRAMACION',
                'old_date' => $oldDate,
                'old_start_time' => $oldStartTime,
                'old_end_time' => $oldEndTime,
                'new_date' => $date,
                'new_start_time' => $startTime,
                'new_end_time' => $endTime,
            ];

            if ($oldProfessionalId !== $professional->id) {
                $historyData['old_professional_id'] = $oldProfessionalId;
                $historyData['new_professional_id'] = $professional->id;
            }

            if ($oldServiceId !== $service->id) {
                $historyData['old_service_id'] = $oldServiceId;
                $historyData['new_service_id'] = $service->id;
            }

            AppointmentHistory::create($historyData);

            return $lockedAppointment->fresh();
        });
    }

    /**
     * Reprograma o reasigna una cita desde recepción. A diferencia del
     * paciente, recepción puede operar fuera de la ventana de 24 horas,
     * pero sigue sujeta a especialidad, disponibilidad y solapamientos.
     */
    public function rescheduleByReception(Appointment $appointment, array $data, User $actor): Appointment
    {
        $this->ensureModifiable($appointment, 'reprogramar');

        $service = $this->findActiveService((int) $appointment->service_id);
        $professional = $this->findActiveProfessional((int) $data['professional_id']);
        $this->ensureProfessionalOffersService($professional, $service);

        $date = $this->normalizeFutureDate($data['appointment_date']);
        $weekday = Carbon::parse($date)->dayOfWeekIso;
        $startTime = $this->normalizeTime($data['start_time']);
        $endTime = $this->addMinutes($startTime, $service->duration_minutes);

        $this->ensureWithinAvailability($professional->id, $weekday, $startTime, $endTime);

        return DB::connection('center')->transaction(function () use ($appointment, $professional, $date, $startTime, $endTime, $actor, $data) {
            $lockedAppointment = Appointment::query()
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureModifiable($lockedAppointment, 'reprogramar');
            $oldProfessionalId = (int) $lockedAppointment->professional_id;
            $isReassignment = $oldProfessionalId !== (int) $professional->id;

            if ($isReassignment && empty(trim((string) ($data['reassignment_reason'] ?? '')))) {
                throw ValidationException::withMessages([
                    'reassignment_reason' => ['Indica el motivo de la reasignación.'],
                ]);
            }

            $this->ensureNoOverlap($professional->id, $date, $startTime, $endTime, $lockedAppointment->id);

            $oldDate = $lockedAppointment->appointment_date->toDateString();
            $oldStartTime = $lockedAppointment->start_time;
            $oldEndTime = $lockedAppointment->end_time;

            $lockedAppointment->update([
                'professional_id' => $professional->id,
                'appointment_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]);

            AppointmentHistory::create([
                'appointment_id' => $lockedAppointment->id,
                'actor_user_id' => $actor->id,
                'event_type' => $isReassignment ? 'REASIGNACION' : 'REPROGRAMACION',
                'old_date' => $oldDate,
                'old_start_time' => $oldStartTime,
                'old_end_time' => $oldEndTime,
                'new_date' => $date,
                'new_start_time' => $startTime,
                'new_end_time' => $endTime,
                'old_professional_id' => $oldProfessionalId,
                'new_professional_id' => $professional->id,
                'reason' => $isReassignment ? trim($data['reassignment_reason']) : null,
            ]);

            return $lockedAppointment->fresh();
        });
    }

    /**
     * Cambia un estado operativo de una cita desde recepción o el profesional asignado.
     * La cita y su evento histórico se guardan atómicamente en la base del centro.
     */
    public function changeStatusByStaff(
        Appointment $appointment,
        string $newStatus,
        User $actor,
        string $role,
        ?int $professionalId,
        ?string $reason = null,
    ): Appointment {
        $allowedStatuses = match ($role) {
            'RECEPCIONISTA' => ['CONFIRMADA', 'ATENDIDA', 'NO_SHOW', 'CANCELADA'],
            'PROFESIONAL' => ['ATENDIDA', 'NO_SHOW'],
            default => [],
        };

        if (! in_array($newStatus, $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                'status' => ['No tienes permiso para aplicar ese estado.'],
            ]);
        }

        return DB::connection('center')->transaction(function () use (
            $appointment,
            $newStatus,
            $actor,
            $role,
            $professionalId,
            $reason
        ) {
            $lockedAppointment = Appointment::query()
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureModifiable($lockedAppointment, 'cambiar el estado');

            if ($lockedAppointment->status === $newStatus) {
                throw ValidationException::withMessages([
                    'status' => ['La cita ya tiene ese estado.'],
                ]);
            }

            if (
                $role === 'PROFESIONAL'
                && (
                    ! $professionalId
                    || (int) $lockedAppointment->professional_id !== $professionalId
                )
            ) {
                abort(403, 'Solo puedes cambiar el estado de tus propias citas.');
            }

            $this->ensureStatusTimeAllowed($lockedAppointment, $newStatus);

            $previousStatus = $lockedAppointment->status;
            $lockedAppointment->update(['status' => $newStatus]);

            AppointmentHistory::create([
                'appointment_id' => $lockedAppointment->id,
                'actor_user_id' => $actor->id,
                'event_type' => $newStatus === 'CANCELADA'
                    ? 'CANCELACION'
                    : 'CAMBIO_ESTADO',
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
            ]);

            return $lockedAppointment->fresh();
        });
    }

    /**
     * Cancela una reserva sin eliminarla físicamente.
     */
    public function cancelAppointment(Appointment $appointment, Patient $patient, ?string $reason, User $actor): Appointment
    {
        return DB::connection('center')->transaction(function () use ($appointment, $patient, $reason, $actor) {
            $lockedAppointment = Appointment::query()
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureOwnership($lockedAppointment, $patient);
            $this->ensureModifiable($lockedAppointment, 'cancelar');
            $this->ensureWithinChangeWindow($lockedAppointment, 'cancelar');
            $previousStatus = $lockedAppointment->status;

            $lockedAppointment->update(['status' => 'CANCELADA']);

            AppointmentHistory::create([
                'appointment_id' => $lockedAppointment->id,
                'actor_user_id' => $actor->id,
                'event_type' => 'CANCELACION',
                'previous_status' => $previousStatus,
                'new_status' => 'CANCELADA',
                'reason' => $reason,
            ]);

            return $lockedAppointment->fresh();
        });
    }

    /**
     * Reservas del paciente autenticado, para la sección "Mis Reservas".
     */
    public function listPatientAppointments(Patient $patient)
    {
        return $patient->appointments()
            ->with(['service.specialty', 'professional'])
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->get();
    }

    /** Reservas visibles para recepción dentro del centro activo. */
    public function listReceptionAppointments()
    {
        return Appointment::query()
            ->with(['patient', 'service.specialty', 'professional'])
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->get();
    }

    private function findActiveService(int $serviceId): Service
    {
        $service = Service::where('is_active', true)->find($serviceId);

        if (! $service) {
            throw ValidationException::withMessages([
                'service_id' => ['La prestación seleccionada no existe o no está disponible.'],
            ]);
        }

        if (! $service->specialty || ! $service->specialty->is_active) {
            throw ValidationException::withMessages([
                'service_id' => ['La especialidad de esta prestación no está disponible.'],
            ]);
        }

        return $service;
    }

    private function findActiveProfessional(int $professionalId): Professional
    {
        $professional = Professional::where('is_active', true)->find($professionalId);

        if (! $professional) {
            throw ValidationException::withMessages([
                'professional_id' => ['El profesional seleccionado no existe o no está disponible.'],
            ]);
        }

        return $professional;
    }

    /**
     * Valida que la especialidad de la prestación esté asociada al
     * profesional (README, sección 13).
     */
    private function ensureProfessionalOffersService(Professional $professional, Service $service): void
    {
        $hasSpecialty = $professional->specialties()
            ->whereKey($service->specialty_id)
            ->exists();

        if (! $hasSpecialty) {
            throw ValidationException::withMessages([
                'professional_id' => ['El profesional seleccionado no atiende esta prestación.'],
            ]);
        }
    }

    /**
     * Impide reservar, reprogramar o consultar disponibilidad en el pasado.
     */
    private function normalizeFutureDate(string $date): string
    {
        $normalized = Carbon::parse($date)->toDateString();

        if (Carbon::parse($normalized)->lt(Carbon::now(self::CENTER_TIMEZONE)->startOfDay())) {
            throw ValidationException::withMessages([
                'appointment_date' => ['No es posible reservar en una fecha pasada.'],
            ]);
        }

        return $normalized;
    }

    /**
     * Verifica que exista un bloque de disponibilidad activo que
     * contenga completamente el intervalo solicitado (README, sección 14).
     */
    private function ensureWithinAvailability(int $professionalId, int $weekday, string $startTime, string $endTime): void
    {
        $exists = Availability::where('professional_id', $professionalId)
            ->where('weekday', $weekday)
            ->where('is_active', true)
            ->where('start_time', '<=', $startTime)
            ->where('end_time', '>=', $endTime)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'start_time' => ['El profesional no tiene disponibilidad en ese horario.'],
            ]);
        }
    }

    /**
     * Verifica que no exista otra reserva activa del mismo profesional
     * que se solape con el intervalo solicitado (README, sección 15).
     */
    private function ensureNoOverlap(int $professionalId, string $date, string $startTime, string $endTime, ?int $excludeAppointmentId = null): void
    {
        $query = Appointment::where('professional_id', $professionalId)
            ->where('appointment_date', $date)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'start_time' => ['El horario seleccionado ya no se encuentra disponible.'],
            ]);
        }
    }

    private function ensureOwnership(Appointment $appointment, Patient $patient): void
    {
        abort_unless(
            $appointment->patient_id === $patient->id,
            403,
            'Esta reserva no pertenece al paciente autenticado.'
        );
    }

    private function ensureModifiable(Appointment $appointment, string $action): void
    {
        if (! in_array($appointment->status, self::BLOCKING_STATUSES, true)) {
            throw ValidationException::withMessages([
                'appointment' => ["No es posible {$action} una reserva en estado {$appointment->status}."],
            ]);
        }
    }

    /**
     * Valida la hora mínima para registrar atención o inasistencia.
     */
    private function ensureStatusTimeAllowed(Appointment $appointment, string $newStatus): void
    {
        $date = $appointment->appointment_date->toDateString();
        $startsAt = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $date.' '.$this->normalizeTime($appointment->start_time),
            self::CENTER_TIMEZONE
        );
        $endsAt = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $date.' '.$this->normalizeTime($appointment->end_time),
            self::CENTER_TIMEZONE
        );
        $now = Carbon::now(self::CENTER_TIMEZONE);

        if ($newStatus === 'ATENDIDA' && $now->lt($startsAt)) {
            throw ValidationException::withMessages([
                'status' => ['La cita solo puede marcarse atendida desde su hora de inicio.'],
            ]);
        }

        if ($newStatus === 'NO_SHOW' && $now->lt($endsAt)) {
            throw ValidationException::withMessages([
                'status' => ['La inasistencia solo puede marcarse después de la hora de término.'],
            ]);
        }
    }

    /**
     * Regla de 24 horas para cancelar o reprogramar (README, sección 21).
     */
    private function ensureWithinChangeWindow(Appointment $appointment, string $action): void
    {
        $appointmentAt = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $appointment->appointment_date->toDateString().' '.$this->normalizeTime($appointment->start_time),
            self::CENTER_TIMEZONE
        );

        $hoursUntil = ($appointmentAt->getTimestamp() - Carbon::now(self::CENTER_TIMEZONE)->getTimestamp()) / 3600;

        if ($hoursUntil < 24) {
            throw ValidationException::withMessages([
                'appointment' => ["Solo puedes {$action} una reserva hasta 24 horas antes de la hora agendada."],
            ]);
        }
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }

    private function addMinutes(string $time, int $minutes): string
    {
        return Carbon::createFromFormat('H:i:s', $time)->addMinutes($minutes)->format('H:i:s');
    }
}
