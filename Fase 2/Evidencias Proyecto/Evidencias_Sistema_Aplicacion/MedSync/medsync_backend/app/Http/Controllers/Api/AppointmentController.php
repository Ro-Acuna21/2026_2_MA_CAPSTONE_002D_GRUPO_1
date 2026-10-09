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

        $rules = [
            'service_id' => ['required', 'integer'],
            'professional_id' => ['required', 'integer'],
            'appointment_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
        ];

        if ($centerUser->role === 'RECEPCIONISTA') {
            $rules['patient_id'] = ['required', 'integer', 'exists:center.patients,id'];
        }

        $data = $request->validate($rules);

        if ($centerUser->role === 'RECEPCIONISTA') {
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

    /**
     * Agenda operativa de recepción. Incluye las reservas del centro
     * resuelto por el middleware de tenant.
     */
    public function index(Request $request)
    {
        abort_unless(
            $this->tenantContext->centerUser()->role === 'RECEPCIONISTA',
            403,
            'No tienes permisos para consultar la agenda del centro.'
        );

        $appointments = $this->appointmentService->listReceptionAppointments();

        return response()->json([
            'data' => $appointments->map(
                fn (Appointment $appointment) => $this->presentAppointment($appointment)
            ),
        ]);
    }

    /**
     * PATCH /api/v1/appointments/{appointment}/reschedule
     */
    public function reschedule(Request $request, Appointment $appointment)
    {
        $centerUser = $this->tenantContext->centerUser();

        if ($centerUser->role === 'RECEPCIONISTA') {
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
     * PATCH /api/v1/appointments/{appointment}/cancel
     */
    public function cancel(Request $request, Appointment $appointment)
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

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
            $centerUser->role === 'PACIENTE' && $centerUser->patient_id,
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
