<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Center\HealthInsurance;
use App\Models\Center\Patient;
use App\Models\Center\PatientAddress;
use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Rules\ValidRut;
use App\Services\Tenants\TenantConnectionResolver;
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
    ) {}

    public function show(Request $request): JsonResponse
    {
        $center = $this->pendingCenter($request);
        return response()->json(['data' => [
            'center' => $center->only(['id', 'name', 'slug']),
            'email' => $request->user()->email,
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $center = $this->pendingCenter($request);
        $user = $request->user();
        abort_if($user->isSuperAdmin() || ! $user->is_active, 403);

        $existingMembership = CenterUser::query()->where('medical_center_id', $center->id)
            ->where('user_id', $user->id)->first();
        if ($existingMembership) {
            if ($existingMembership->role !== 'PACIENTE' || ! $existingMembership->is_active) {
                throw ValidationException::withMessages(['center' => ['Tu cuenta ya tiene otro acceso o está inactiva en este centro.']]);
            }
            $this->activateSession($request, $center);
            return response()->json(['data' => ['center' => $center->only(['id', 'name', 'slug']), 'patient_id' => $existingMembership->patient_id]]);
        }

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
        if (Patient::query()->where('rut', $rut)
            ->orWhereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($user->email))])->exists()) {
            throw ValidationException::withMessages([
                'rut' => ['Ya existe una ficha con este RUT o correo en el centro. Contacta a recepción para verificar tu identidad.'],
            ]);
        }
        $insurance = HealthInsurance::query()->where('name', $data['health_insurance'])->first();
        if (! $insurance) {
            throw ValidationException::withMessages(['health_insurance' => ['Previsión no disponible en este centro.']]);
        }
        $address = $this->addressData($data);
        $membership = null;
        try {
            DB::connection('center')->transaction(function () use ($data, $rut, $user, $center, $insurance, $address, &$membership) {
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
                $membership = DB::connection('core')->transaction(fn () => CenterUser::create([
                    'medical_center_id' => $center->id,
                    'user_id' => $user->id,
                    'role' => 'PACIENTE',
                    'patient_id' => $patient->id,
                    'is_active' => true,
                ]));
            });
        } catch (Throwable $exception) {
            if ($membership !== null) {
                CenterUser::query()->whereKey($membership->id)->where('patient_id', $membership->patient_id)->delete();
            }
            throw $exception;
        }

        $this->activateSession($request, $center);
        return response()->json(['data' => [
            'center' => $center->only(['id', 'name', 'slug']),
            'patient_id' => $membership->patient_id,
        ]], 201);
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
