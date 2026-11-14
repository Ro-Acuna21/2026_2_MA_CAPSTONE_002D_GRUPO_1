<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Center\Appointment;
use App\Models\Center\Patient;
use App\Models\Center\Service;
use App\Models\Center\ServicePrice;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PatientBillingController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function appointment(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorizeAppointment($request, $appointment);

        return response()->json(['data' => DB::connection('center')->table('care_indications as i')
            ->join('services as s', 's.id', '=', 'i.service_id')
            ->where('i.appointment_id', $appointment->id)
            ->orderBy('i.id')
            ->get(['i.id', 'i.service_id', 's.name as service_name', 'i.amount_clp', 'i.status', 'i.created_at'])]);
    }

    public function indicate(Request $request, Appointment $appointment): JsonResponse
    {
        $member = $this->tenant->centerUser();
        abort_unless($member->role === 'PROFESIONAL' && (int) $member->professional_id === (int) $appointment->professional_id, 403);
        $data = $request->validate(['service_id' => ['required', 'integer', 'exists:center.services,id']]);
        $service = Service::query()->whereKey($data['service_id'])->where('is_active', true)->firstOrFail();
        $price = ServicePrice::query()->where('service_id', $service->id)
            ->whereDate('effective_from', '<=', today())
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()))
            ->orderByDesc('effective_from')->first();
        if (! $price) {
            throw ValidationException::withMessages(['service_id' => ['La prestación no tiene un precio vigente en este centro.']]);
        }

        $indication = DB::connection('center')->transaction(function () use ($appointment, $service, $price, $request) {
            $locked = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'ATENDIDA') {
                throw ValidationException::withMessages(['appointment' => ['La cita debe estar atendida para indicar una prestación.']]);
            }
            if (DB::connection('center')->table('care_indications')
                ->where('appointment_id', $locked->id)->where('service_id', $service->id)->exists()) {
                throw ValidationException::withMessages(['service_id' => ['Esta prestación ya fue indicada en la cita.']]);
            }
            $id = DB::connection('center')->table('care_indications')->insertGetId([
                'appointment_id' => $locked->id, 'patient_id' => $locked->patient_id,
                'professional_id' => $locked->professional_id, 'service_id' => $service->id,
                'amount_clp' => $price->amount_clp, 'status' => 'INDICATED',
                'indicated_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return DB::connection('center')->table('care_indications')->where('id', $id)->first();
        });

        return response()->json(['data' => $indication], 201);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorizeStaff();
        $db = DB::connection('center');
        $indications = $db->table('care_indications as i')->join('services as s', 's.id', '=', 'i.service_id')
            ->join('patients as p', 'p.id', '=', 'i.patient_id')
            ->orderByDesc('i.created_at')->get([
                'i.id', 'i.patient_id', 'i.appointment_id', 'i.service_id', 's.name as service_name',
                'i.amount_clp', 'i.status', 'i.created_at', 'p.first_name', 'p.last_name',
            ]);
        $payments = $db->table('patient_payments')->whereNull('voided_at')->orderByDesc('id')->get();
        $chargesByPatient = $indications->where('status', 'CHARGED')->groupBy('patient_id');
        $paymentsByPatient = $payments->groupBy('patient_id');
        $patientIds = $chargesByPatient->keys()->merge($paymentsByPatient->keys())->unique();
        $patients = Patient::query()->whereIn('id', $patientIds)->orderBy('first_name')->orderBy('last_name')->get()
            ->map(function (Patient $patient) use ($chargesByPatient, $paymentsByPatient) {
                $charged = (int) ($chargesByPatient[$patient->id] ?? collect())->sum('amount_clp');
                $paid = (int) ($paymentsByPatient[$patient->id] ?? collect())->sum('amount_clp');
                return [
                    'id' => $patient->id, 'name' => trim($patient->first_name.' '.$patient->last_name),
                    'rut' => $patient->rut, 'charged_clp' => $charged, 'paid_clp' => $paid,
                    'balance_clp' => $charged - $paid,
                ];
            });

        return response()->json(['data' => [
            'charged_clp' => $patients->sum('charged_clp'),
            'paid_clp' => $patients->sum('paid_clp'),
            'balance_clp' => $patients->sum('balance_clp'),
            'pending_indications' => $indications->where('status', 'INDICATED')->count(),
            'patients' => $patients,
            'indications' => $indications,
            'payments' => $payments,
        ]]);
    }

    public function confirm(Request $request, int $indication): JsonResponse
    {
        $this->authorizeStaff();
        $db = DB::connection('center');
        $record = $db->table('care_indications')->where('id', $indication)->first();
        abort_unless($record, 404);
        $updated = $db->transaction(function () use ($db, $record, $request) {
            Patient::query()->whereKey($record->patient_id)->lockForUpdate()->firstOrFail();
            $current = $db->table('care_indications')->where('id', $record->id)->lockForUpdate()->first();
            if ($current->status !== 'INDICATED' || Appointment::findOrFail($current->appointment_id)->status !== 'ATENDIDA') {
                throw ValidationException::withMessages(['indication' => ['La indicación no está pendiente en una cita atendida.']]);
            }
            $db->table('care_indications')->where('id', $current->id)->update([
                'status' => 'CHARGED', 'confirmed_at' => now(),
                'confirmed_by' => $request->user()->id, 'updated_at' => now(),
            ]);
            return $db->table('care_indications')->where('id', $current->id)->first();
        });
        return response()->json(['data' => $updated]);
    }

    public function voidIndication(Request $request, int $indication): JsonResponse
    {
        $this->authorizeStaff();
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']]);
        $db = DB::connection('center');
        $record = $db->table('care_indications')->where('id', $indication)->first();
        abort_unless($record, 404);
        $updated = $db->transaction(function () use ($db, $record, $data, $request) {
            Patient::query()->whereKey($record->patient_id)->lockForUpdate()->firstOrFail();
            $current = $db->table('care_indications')->where('id', $record->id)->lockForUpdate()->first();
            if ($current->status === 'VOIDED') {
                throw ValidationException::withMessages(['indication' => ['La indicación ya fue anulada.']]);
            }
            if ($current->status === 'CHARGED' && $this->balance($record->patient_id)['balance'] < $current->amount_clp) {
                throw ValidationException::withMessages(['indication' => ['Los abonos superan los cargos restantes. Revisa los abonos antes de anular este cargo.']]);
            }
            $db->table('care_indications')->where('id', $current->id)->update([
                'status' => 'VOIDED', 'voided_at' => now(), 'voided_by' => $request->user()->id,
                'void_reason' => trim($data['reason']), 'updated_at' => now(),
            ]);
            return $db->table('care_indications')->where('id', $current->id)->first();
        });
        return response()->json(['data' => $updated]);
    }

    public function pay(Request $request, Patient $patient): JsonResponse
    {
        $this->authorizeStaff();
        $data = $request->validate([
            'amount_clp' => ['required', 'integer', 'min:1', 'max:999999999'],
            'method' => ['required', 'in:CASH,CARD,TRANSFER,OTHER'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);
        $payment = DB::connection('center')->transaction(function () use ($patient, $data, $request) {
            Patient::query()->whereKey($patient->id)->lockForUpdate()->firstOrFail();
            if ($data['amount_clp'] > $this->balance($patient->id)['balance']) {
                throw ValidationException::withMessages(['amount_clp' => ['El abono supera el saldo pendiente.']]);
            }
            $id = DB::connection('center')->table('patient_payments')->insertGetId([
                'patient_id' => $patient->id, 'amount_clp' => $data['amount_clp'],
                'method' => $data['method'], 'reference' => $data['reference'] ?? null,
                'recorded_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return DB::connection('center')->table('patient_payments')->where('id', $id)->first();
        });
        return response()->json(['data' => $payment], 201);
    }

    public function voidPayment(Request $request, int $payment): JsonResponse
    {
        abort_unless($this->tenant->centerUser()->role === 'ADMIN', 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']]);
        $db = DB::connection('center');
        $record = $db->table('patient_payments')->where('id', $payment)->first();
        abort_unless($record, 404);
        $updated = $db->transaction(function () use ($db, $record, $data, $request) {
            Patient::query()->whereKey($record->patient_id)->lockForUpdate()->firstOrFail();
            $current = $db->table('patient_payments')->where('id', $record->id)->lockForUpdate()->first();
            if ($current->voided_at !== null) {
                throw ValidationException::withMessages(['payment' => ['El pago ya fue anulado.']]);
            }
            $db->table('patient_payments')->where('id', $record->id)->update([
                'voided_at' => now(), 'voided_by' => $request->user()->id,
                'void_reason' => trim($data['reason']), 'updated_at' => now(),
            ]);
            return $db->table('patient_payments')->where('id', $record->id)->first();
        });
        return response()->json(['data' => $updated]);
    }

    private function balance(int $patientId): array
    {
        $db = DB::connection('center');
        $charged = (int) $db->table('care_indications')->where('patient_id', $patientId)
            ->where('status', 'CHARGED')->sum('amount_clp');
        $paid = (int) $db->table('patient_payments')->where('patient_id', $patientId)
            ->whereNull('voided_at')->sum('amount_clp');
        return ['charged' => $charged, 'paid' => $paid, 'balance' => $charged - $paid];
    }

    private function authorizeStaff(): void
    {
        abort_unless(in_array($this->tenant->centerUser()->role, ['ADMIN', 'RECEPCIONISTA'], true), 403);
    }

    private function authorizeAppointment(Request $request, Appointment $appointment): void
    {
        $member = $this->tenant->centerUser();
        abort_unless(in_array($member->role, ['ADMIN', 'RECEPCIONISTA'], true)
            || ($member->role === 'PROFESIONAL' && (int) $member->professional_id === (int) $appointment->professional_id)
            || ($member->role === 'PACIENTE' && (int) $member->patient_id === (int) $appointment->patient_id), 403);
    }
}
