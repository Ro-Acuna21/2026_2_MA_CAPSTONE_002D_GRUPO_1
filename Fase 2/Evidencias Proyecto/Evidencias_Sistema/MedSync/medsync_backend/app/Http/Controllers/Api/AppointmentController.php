<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Center\Appointment;
use App\Models\Center\Patient;
use App\Models\Center\Professional;
use App\Models\Center\Service;
use App\Services\Appointments\AppointmentService;
use App\Support\TenantContext;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * GET /api/v1/appointments/available-slots
     *
     * Devuelve los horarios reservables para un profesional y una
     * prestación en una fecha determinada (README, sección 23).
     */
    public function availableSlots(Request $request)
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'professional_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $service = Service::findOrFail($data['service_id']);
        $professional = Professional::findOrFail($data['professional_id']);

        $slots = $this->appointmentService->getAvailableSlots(
            $professional,
            $service,
            $data['date']
        );

        return response()->json([
            'data' => [
                'date' => $data['date'],
                'professional_id' => $professional->id,
                'service_id' => $service->id,
                'duration_minutes' => $service->duration_minutes,
                'slots' => $slots,
            ],
        ]);
    }

    /**
     * POST /api/v1/appointments
     *
     * Crea una reserva para el paciente autenticado (README, sección 24).
     */
    public function store(Request $request)
    {
        $centerUser = $this->tenantContext->centerUser();
        $isReception = $request->user()->can('appointments.create.for_patient');
        abort_unless($isReception || $request->user()->can('appointments.create.own'), 403);

        $rules = [
            'service_id' => ['required', 'integer'],
            'professional_id' => ['required', 'integer'],
            'appointment_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
        ];

        if ($isReception) {
            $rules['patient_id'] = ['required', 'integer', 'exists:center.patients,id'];
        }

        $data = $request->validate($rules);

        if ($isReception) {
            $patient = Patient::where('is_active', true)->findOrFail($data['patient_id']);
            $source = 'RECEPCION';
        } else {
            $patient = $this->resolvePatient($request);
            $source = 'WEB';
        }

        $appointment = $this->appointmentService->createAppointment(
            $patient,
            $data,
            $request->user(),
            $source,
        );

        return response()->json([
            'message' => 'La reserva fue creada correctamente.',
            'data' => $this->presentAppointment($appointment),
        ], 201);
    }

    /**
     * GET /api/v1/appointments/my
     *
     * Reservas del paciente autenticado ("Mis Reservas", README sección 25).
     */
    public function myAppointments(Request $request)
    {
        $patient = $this->resolvePatient($request);

        $appointments = $this->appointmentService->listPatientAppointments($patient);

        return response()->json([
            'data' => $appointments->map(
                fn (Appointment $appointment) => $this->presentAppointment($appointment)
            ),
        ]);
    }

    /** Agenda operativa de recepción o del profesional autenticado. */
    public function index(Request $request)
    {
        $centerUser = $this->tenantContext->centerUser();

        if ($request->user()->can('appointments.view.all')) {
            $appointments = $this->appointmentService->listReceptionAppointments();
        } elseif (
            $centerUser->role === 'PROFESIONAL'
            && $centerUser->professional_id
            && $request->user()->can('appointments.view.own')
        ) {
            $professional = Professional::findOrFail($centerUser->professional_id);
            $appointments = $this->appointmentService->listProfessionalAppointments($professional);
        } else {
            abort(403, 'No tienes permisos para consultar esta agenda.');
        }

        return response()->json([
            'data' => $appointments->map(
                fn (Appointment $appointment) => $this->presentAppointment($appointment)
            ),
        ]);
    }

    /** Historial inmutable de una cita del centro activo. */
    public function history(Request $request, Appointment $appointment)
    {
        $member = $this->tenantContext->centerUser();
        $allowed = ($member->role === 'RECEPCIONISTA' && $request->user()->can('appointments.view.all'))
            || ($member->role === 'PROFESIONAL' && $member->professional_id === $appointment->professional_id && $request->user()->can('appointments.view.own'))
            || ($member->role === 'PACIENTE' && $member->patient_id === $appointment->patient_id && $request->user()->can('appointments.view.own'));
        abort_unless($allowed, 403);

        $events = $appointment->history()->reorder('created_at', 'desc')->orderByDesc('id')->get();
        $professionalIds = $events->flatMap(fn ($event) => [$event->old_professional_id, $event->new_professional_id])->filter()->unique();
        $names = Professional::withTrashed()->whereIn('id', $professionalIds)->get()->mapWithKeys(
            fn ($professional) => [$professional->id => trim($professional->first_name.' '.$professional->last_name)]
        );

        return response()->json([
            'data' => [
                'appointment' => $this->presentAppointment($appointment),
                'events' => $events->map(fn ($event) => [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'previous_status' => $event->previous_status,
                    'new_status' => $event->new_status,
                    'old_date' => $event->old_date?->toDateString(),
                    'new_date' => $event->new_date?->toDateString(),
                    'old_professional_id' => $event->old_professional_id,
                    'new_professional_id' => $event->new_professional_id,
                    'old_professional_name' => $names[$event->old_professional_id] ?? null,
                    'new_professional_name' => $names[$event->new_professional_id] ?? null,
                    'reason' => $member->role === 'PACIENTE' ? null : $event->reason,
                    'created_at' => $event->created_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * PATCH /api/v1/appointments/{appointment}/reschedule
     */
    public function reschedule(Request $request, Appointment $appointment)
    {
        $centerUser = $this->tenantContext->centerUser();

        if ($request->user()->can('appointments.reschedule.for_patient')) {
            $data = $request->validate([
                'professional_id' => ['required', 'integer'],
                'reassignment_reason' => ['nullable', 'string', 'max:300'],
                'appointment_date' => ['required', 'date_format:Y-m-d'],
                'start_time' => ['required', 'date_format:H:i'],
            ]);

            $appointment = $this->appointmentService->rescheduleByReception(
                $appointment,
                $data,
                $request->user()
            );
        } else {
            abort_unless($request->user()->can('appointments.reschedule.own'), 403);
            abort_unless(
                $centerUser->role === 'PACIENTE',
                403,
                'No tienes permisos para reprogramar citas.'
            );

            $data = $request->validate([
                'professional_id' => ['prohibited'],
                'reassignment_reason' => ['prohibited'],
                'service_id' => ['prohibited'],
                'appointment_date' => ['required', 'date_format:Y-m-d'],
                'start_time' => ['required', 'date_format:H:i'],
            ]);

            $appointment = $this->appointmentService->rescheduleAppointment(
                $appointment,
                $this->resolvePatient($request),
                $data,
                $request->user()
            );
        }

        return response()->json([
            'message' => 'La reserva fue reprogramada correctamente.',
            'data' => $this->presentAppointment($appointment),
        ]);
    }

    /**
     * PATCH /api/v1/appointments/{appointment}/status
     */
    public function updateStatus(Request $request, Appointment $appointment)
    {
        $centerUser = $this->tenantContext->centerUser();

        $statusPermission = $centerUser->role === 'PROFESIONAL'
            ? 'appointments.status.manage.own'
            : 'appointments.status.manage';
        abort_unless(
            in_array($centerUser->role, ['RECEPCIONISTA', 'PROFESIONAL'], true)
                && $request->user()->can($statusPermission),
            403,
            'No tienes permisos para cambiar el estado de esta cita.'
        );

        $data = $request->validate([
            'status' => ['required', 'string', 'in:CONFIRMADA,ATENDIDA,NO_SHOW,CANCELADA'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $appointment = $this->appointmentService->changeStatusByStaff(
            $appointment,
            $data['status'],
            $request->user(),
            $centerUser->role,
            $centerUser->professional_id ? (int) $centerUser->professional_id : null,
            $data['reason'] ?? null,
        );

        return response()->json([
            'message' => 'El estado de la cita fue actualizado.',
            'data' => $this->presentAppointment($appointment),
        ]);
    }

    /**
     * PATCH /api/v1/appointments/{appointment}/cancel
     */
    public function cancel(Request $request, Appointment $appointment)
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless($request->user()->can('appointments.cancel.own'), 403);
        $patient = $this->resolvePatient($request);

        $appointment = $this->appointmentService->cancelAppointment(
            $appointment,
            $patient,
            $data['reason'] ?? null,
            $request->user()
        );

        return response()->json([
            'message' => 'La reserva fue cancelada correctamente.',
            'data' => $this->presentAppointment($appointment),
        ]);
    }

    /**
     * Resuelve la ficha Patient del usuario autenticado.
     *
     * El paciente no puede enviar otro patient_id para consultar o
     * crear reservas ajenas: siempre se resuelve desde la sesión
     * (README, sección 25).
     */
    private function resolvePatient(Request $request): Patient
    {
        $centerUser = $this->tenantContext->centerUser();

        abort_unless(
            $request->user()->can('patients.view.own') && $centerUser->patient_id,
            403,
            'No tienes un perfil de paciente asociado a tu cuenta.'
        );

        return Patient::findOrFail($centerUser->patient_id);
    }

    private function presentAppointment(Appointment $appointment): array
    {
        $appointment->loadMissing(['patient', 'service.specialty', 'professional']);

        return [
            'id' => $appointment->id,
            'appointment_date' => $appointment->appointment_date->toDateString(),
            'start_time' => substr($appointment->start_time, 0, 5),
            'end_time' => substr($appointment->end_time, 0, 5),
            'status' => $appointment->status,
            'source' => $appointment->source,
            'overbook' => $appointment->overbook,
            'note' => $appointment->note,

            'patient' => $appointment->patient ? [
                'id' => $appointment->patient->id,
                'first_name' => $appointment->patient->first_name,
                'last_name' => $appointment->patient->last_name,
            ] : null,
            'service' => $appointment->service ? [
                'id' => $appointment->service->id,
                'name' => $appointment->service->name,
                'duration_minutes' => $appointment->service->duration_minutes,
                'specialty' => $appointment->service->specialty ? [
                    'id' => $appointment->service->specialty->id,
                    'name' => $appointment->service->specialty->name,
                ] : null,
            ] : null,
            'professional' => $appointment->professional ? [
                'id' => $appointment->professional->id,
                'first_name' => $appointment->professional->first_name,
                'last_name' => $appointment->professional->last_name,
            ] : null,
        ];
    }
}
