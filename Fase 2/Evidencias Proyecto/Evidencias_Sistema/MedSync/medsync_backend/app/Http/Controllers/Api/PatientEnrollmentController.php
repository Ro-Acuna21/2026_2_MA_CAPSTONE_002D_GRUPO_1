<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Center\HealthInsurance;
use App\Models\Center\Patient;
use App\Models\Center\PatientAddress;
use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Models\User;
use App\Rules\ValidRut;
use App\Services\Tenants\TenantConnectionResolver;
use App\Services\Patients\PatientMembershipIntegrity;
use App\Services\TerritorialCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PatientEnrollmentController extends Controller
{
    public function __construct(
        private readonly TenantConnectionResolver $resolver,
        private readonly TerritorialCatalog $territorial,
        private readonly PatientMembershipIntegrity $integrity,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $center = $this->pendingCenter($request);
        $user = $request->user();
        return response()->json(['data' => [
            'center' => $center->only(['id', 'name', 'slug']),
            'email' => $user->email,
            'name' => $user->name,
            'prefill' => $this->verifiedPersonalData($user, $center),
        ]]);
    }

    private function verifiedPersonalData(User $user, MedicalCenter $pendingCenter): ?array
    {
        $originalDatabase = config('database.connections.center.database');
        $memberships = CenterUser::query()->with('medicalCenter')
            ->where('user_id', $user->id)->where('role', 'PACIENTE')
            ->where('is_active', true)->whereNotNull('patient_id')
            ->where('medical_center_id', '!=', $pendingCenter->id)
            ->orderByDesc('id')->get();

        foreach ($memberships as $membership) {
            $sourceCenter = $membership->medicalCenter;
            if (! $sourceCenter || ! $sourceCenter->is_active || $sourceCenter->provisioning_status !== 'READY') {
                continue;
            }
            try {
                $this->resolver->connect($sourceCenter);
                if (! $this->integrity->valid($membership, $user)) {
                    continue;
                }
                $patient = Patient::query()->find($membership->patient_id);
                return [
                    'first_name' => $patient->first_name,
                    'last_name' => $patient->last_name,
                    'rut' => $patient->rut,
                    'birth_date' => $patient->birth_date?->toDateString(),
                    'phone' => $patient->phone,
                ];
            } catch (Throwable $exception) {
                report($exception);
            } finally {
                // The next tenant request resolves its own connection. Restoring the
                // configured name avoids leaving this request pointed at the source.
                config(['database.connections.center.database' => $originalDatabase]);
            }
        }

        return null;
    }

    public function store(Request $request): JsonResponse
    {
        $center = $this->pendingCenter($request);
        $user = $request->user();
        abort_if($user->isSuperAdmin() || ! $user->is_active, 403);

        $request->merge(['phone' => preg_replace('/[\s()-]/', '', (string) $request->input('phone'))]);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'rut' => ['required', 'string', new ValidRut()],
            'birth_date' => ['required', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'phone' => ['required', 'string', 'regex:/^(?:\+?56)?[2-9]\d{8}$/'],
            'health_insurance' => ['required', Rule::in(['Fonasa', 'Isapre', 'Particular', 'Otra'])],
            'medical_insurance' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:200'],
            'region_code' => ['nullable', 'string', 'regex:/^\d{2}$/'],
            'commune_code' => ['nullable', 'string', 'regex:/^\d{5}$/'],
            'consent' => ['required', 'accepted'],
        ]);

        $this->resolver->connect($center);
        $rut = ValidRut::normalize($data['rut']);
        $insurance = HealthInsurance::query()->where('name', $data['health_insurance'])->first();
        if (! $insurance) {
            throw ValidationException::withMessages(['health_insurance' => ['Previsión no disponible en este centro.']]);
        }
        $address = $this->addressData($data);
        // Lock the global account to serialize retries even when no membership exists.
        // The center commits first. If the Core commit then fails, a retry can find
        // the patient by verified account, RUT and birth date without duplicating it.
        [$membership, $created] = DB::connection('core')->transaction(function () use ($data, $rut, $user, $center, $insurance, $address) {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $membership = CenterUser::query()->where('medical_center_id', $center->id)
                ->where('user_id', $user->id)->lockForUpdate()->first();
            if ($membership && ($membership->role !== 'PACIENTE' || ! $membership->is_active)) {
                throw ValidationException::withMessages(['center' => ['Tu cuenta ya tiene otro acceso o está inactiva en este centro.']]);
            }
            if ($membership && $this->integrity->valid($membership, $user)) {
                return [$membership, false];
            }

            [$patient, $created] = DB::connection('center')->transaction(function () use ($data, $rut, $user, $insurance, $address) {
                $matches = Patient::withTrashed()->where('rut', $rut)
                    ->orWhereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($user->email))])
                    ->lockForUpdate()->get();
                if ($matches->count() > 1 || $matches->contains(function (Patient $patient) use ($rut, $user, $data) {
                    return $patient->trashed() || ! $patient->is_active
                        || $patient->rut !== $rut
                        || (int) $patient->user_id !== (int) $user->id
                        || strtolower(trim((string) $patient->email)) !== strtolower(trim($user->email))
                        || $patient->birth_date?->toDateString() !== $data['birth_date'];
                })) {
                    throw ValidationException::withMessages([
                        'rut' => ['Ya existe una ficha con este RUT o correo. Contacta a recepción para verificar tu identidad.'],
                    ]);
                }

                $patient = $matches->first();
                if ($patient) {
                    return [$patient, false];
                }
                $patient = Patient::create([
                    'user_id' => $user->id,
                    'health_insurance_id' => $insurance->id,
                    'first_name' => trim($data['first_name']),
                    'last_name' => trim($data['last_name']),
                    'rut' => $rut,
                    'birth_date' => $data['birth_date'],
                    'email' => $user->email,
                    'phone' => $data['phone'],
                    'medical_insurance' => $data['medical_insurance'] ?? null,
                    'consent_at' => now(),
                    'consent_version' => 'v1',
                    'is_active' => true,
                ]);
                if ($address !== null) {
                    PatientAddress::create(['patient_id' => $patient->id, 'is_primary' => true, ...$address]);
                }
                return [$patient, true];
            });

            if ($membership) {
                $membership->update(['patient_id' => $patient->id]);
            } else {
                $membership = CenterUser::create([
                    'medical_center_id' => $center->id,
                    'user_id' => $user->id,
                    'role' => 'PACIENTE',
                    'patient_id' => $patient->id,
                    'is_active' => true,
                ]);
            }
            return [$membership, $created];
        });

        $this->activateSession($request, $center);
        return response()->json(['data' => [
            'center' => $center->only(['id', 'name', 'slug']),
            'patient_id' => $membership->patient_id,
        ]], $created ? 201 : 200);
    }

    private function pendingCenter(Request $request): MedicalCenter
    {
        abort_unless($request->hasSession(), 401);
        $id = $request->session()->get('pending_patient_center_id')
            ?? $request->session()->get('active_medical_center_id');
        $center = $id ? MedicalCenter::query()->whereKey($id)->where('is_active', true)->first() : null;
        abort_unless($center, 403, 'No hay un centro pendiente para esta cuenta.');
        return $center;
    }

    private function activateSession(Request $request, MedicalCenter $center): void
    {
        $request->session()->forget('pending_patient_center_id');
        $request->session()->put('active_medical_center_id', $center->id);
    }

    private function addressData(array $data): ?array
    {
        $line = trim((string) ($data['address'] ?? ''));
        $region = trim((string) ($data['region_code'] ?? ''));
        $commune = trim((string) ($data['commune_code'] ?? ''));
        if ($line === '' && $region === '' && $commune === '') return null;
        if ($line === '') {
            throw ValidationException::withMessages(['address' => ['Ingresa la calle y número para guardar la dirección.']]);
        }
        $location = $this->territorial->resolve($region, $commune);
        return ['address_line' => $line, 'region' => $location['region'] ?? null, 'commune' => $location['commune'] ?? null];
    }
}
