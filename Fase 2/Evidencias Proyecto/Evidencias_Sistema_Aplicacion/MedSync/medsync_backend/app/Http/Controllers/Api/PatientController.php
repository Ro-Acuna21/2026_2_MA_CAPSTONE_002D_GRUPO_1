<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Center\HealthInsurance;
use App\Models\Center\Patient;
use App\Models\Center\PatientAddress;
use App\Rules\ValidRut;
use App\Support\TenantContext;
use App\Services\TerritorialCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PatientController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TerritorialCatalog $territorialCatalog
    ) {}

    public function index(Request $request)
    {
        $this->requireReception($request);

        return response()->json(['data' => Patient::query()
            ->with(['healthInsurance', 'primaryAddress'])
            ->where('is_active', true)->orderBy('first_name')->orderBy('last_name')->get()
            ->map(fn (Patient $patient) => $this->present($patient))]);
    }

    public function show(Request $request, int $patient)
    {
        $this->authorizePatient($patient, $request);

        return response()->json(['data' => $this->present($this->findPatient($patient))]);
    }

    public function store(Request $request)
    {
        $this->requireReception($request);
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
            'phone' => preg_replace('/[\s()-]/', '', (string) $request->input('phone')),
        ]);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:100'],
            'last_name' => ['required', 'string', 'min:2', 'max:100'],
            'rut' => ['required', 'string', new ValidRut()],
            'birth_date' => ['required', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^(?:\+?56)?[2-9]\d{8}$/'],
            'health_insurance' => ['required', Rule::in(['Fonasa', 'Isapre', 'Particular', 'Otra'])],
            'medical_insurance' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:200'],
            'region_code' => ['nullable', 'string', 'regex:/^\d{2}$/'],
            'commune_code' => ['nullable', 'string', 'regex:/^\d{5}$/'],
            'consent' => ['required', 'accepted'],
        ]);
        $addressData = $this->patientAddressData($data);
        $rut = ValidRut::normalize($data['rut']);
        if (Patient::withTrashed()->where('rut', $rut)->exists()) {
            throw ValidationException::withMessages(['rut' => ['Ya existe una ficha con este RUT en el centro.']]);
        }
        $this->ensureUniqueEmail($data['email']);
        $insurance = $this->insurance($data['health_insurance']);

        $patient = DB::connection('center')->transaction(function () use ($data, $rut, $insurance, $addressData) {
            $patient = Patient::create([
                'first_name' => trim($data['first_name']), 'last_name' => trim($data['last_name']),
                'rut' => $rut, 'birth_date' => $data['birth_date'], 'email' => $data['email'],
                'phone' => $data['phone'], 'health_insurance_id' => $insurance->id,
                'medical_insurance' => $data['medical_insurance'] ?? null,
                'consent_at' => now(), 'consent_version' => 'v1', 'is_active' => true,
            ]);
            if ($addressData !== null) {
                PatientAddress::create($addressData + ['patient_id' => $patient->id, 'is_primary' => true]);
            }
            return $patient;
        });

        return response()->json(['data' => $this->present($patient->load(['healthInsurance', 'primaryAddress']))], 201);
    }

    public function update(Request $request, int $patient)
    {
        $this->authorizePatient($patient, $request);
        $record = $this->findPatient($patient);
        $isReception = $request->user()->can('patients.manage');
        abort_unless($isReception || $request->user()->can('patients.update.own'), 403);
        if ($request->has('phone')) {
            $request->merge(['phone' => preg_replace('/[\s()-]/', '', (string) $request->input('phone'))]);
        }
        $rules = [
            'rut' => ['prohibited'],
            'consent' => ['prohibited'],
            'consent_at' => ['prohibited'],
            'user_id' => ['prohibited'],
            'is_active' => ['prohibited'],
            'first_name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'phone' => ['sometimes', 'required', 'string', 'regex:/^(?:\+?56)?[2-9]\d{8}$/'],
            'address' => ['sometimes', 'nullable', 'string', 'max:200'],
            'region_code' => ['sometimes', 'nullable', 'string', 'regex:/^\d{2}$/'],
            'commune_code' => ['sometimes', 'nullable', 'string', 'regex:/^\d{5}$/'],
        ];
        if ($isReception) {
            $rules += [
                'email' => ['sometimes', 'required', 'email', 'max:150'],
                'birth_date' => ['sometimes', 'required', 'date', 'before_or_equal:today', 'after:1900-01-01'],
                'health_insurance' => ['sometimes', Rule::in(['Fonasa', 'Isapre', 'Particular', 'Otra'])],
                'medical_insurance' => ['sometimes', 'nullable', 'string', 'max:100'],
            ];
        } else {
            $rules += [
                'email' => ['prohibited'],
                'birth_date' => ['prohibited'],
                'health_insurance' => ['prohibited'],
                'medical_insurance' => ['prohibited'],
            ];
        }
        $data = $request->validate($rules);
        $addressData = $this->updatedPatientAddressData($data, $record->primaryAddress);
        if (array_key_exists('email', $data)) {
            $data['email'] = strtolower(trim($data['email']));
            if ($record->user_id && $data['email'] !== $record->email) {
                throw ValidationException::withMessages(['email' => ['El correo de una cuenta activa no se puede modificar desde la ficha.']]);
            }
            $this->ensureUniqueEmail($data['email'], $record->id);
        }
        if (isset($data['health_insurance'])) {
            $data['health_insurance_id'] = $this->insurance($data['health_insurance'])->id;
        }

        DB::connection('center')->transaction(function () use ($record, $data, $addressData) {
            $record->update(collect($data)->except(['address', 'region_code', 'commune_code', 'health_insurance'])->all());
            if ($addressData !== null) {
                if ($addressData === []) {
                    $record->primaryAddress()->delete();
                } else {
                    PatientAddress::updateOrCreate(
                        ['patient_id' => $record->id, 'is_primary' => true],
                        $addressData,
                    );
                }
            }
        });

        return response()->json(['data' => $this->present($record->fresh(['healthInsurance', 'primaryAddress']))]);
    }

    private function requireReception(Request $request): void
    {
        abort_unless(
            $this->tenantContext->hasCenter() && $request->user()?->can('patients.manage'),
            403
        );
    }

    private function authorizePatient(int $patient, Request $request): void
    {
        abort_unless($this->tenantContext->hasCenter(), 403);
        $membership = $this->tenantContext->centerUser();
        $canManagePatients = $request->user()?->can('patients.manage');
        $canViewOwnPatient = $request->user()?->can('patients.view.own')
            && (int) $membership->patient_id === $patient;

        abort_unless($canManagePatients || $canViewOwnPatient, 403);
    }

    private function findPatient(int $patient): Patient
    {
        return Patient::with(['healthInsurance', 'primaryAddress'])->where('is_active', true)->findOrFail($patient);
    }

    private function insurance(string $name): HealthInsurance
    {
        $insurance = HealthInsurance::where('name', $name)->where('is_active', true)->first();
        if (! $insurance) {
            throw ValidationException::withMessages(['health_insurance' => ['Previsión no disponible en este centro.']]);
        }
        return $insurance;
    }

    /** @return array{address_line: string, region: ?string, commune: ?string}|null */
    private function patientAddressData(array $data): ?array
    {
        $address = trim((string) ($data['address'] ?? ''));
        $regionCode = trim((string) ($data['region_code'] ?? ''));
        $communeCode = trim((string) ($data['commune_code'] ?? ''));

        if ($address === '' && $regionCode === '' && $communeCode === '') {
            return null;
        }

        $location = $this->territorialCatalog->resolve($regionCode, $communeCode);
        if ($address === '') {
            throw ValidationException::withMessages([
                'address' => ['Ingresa la calle y número para guardar la dirección.'],
            ]);
        }

        return [
            'address_line' => $address,
            'region' => $location['region'] ?? null,
            'commune' => $location['commune'] ?? null,
        ];
    }

    /** @return array{address_line: string, region: ?string, commune: ?string}|array{}|null */
    private function updatedPatientAddressData(array $data, ?PatientAddress $existing): ?array
    {
        $addressWasSent = array_key_exists('address', $data);
        $regionCode = trim((string) ($data['region_code'] ?? ''));
        $communeCode = trim((string) ($data['commune_code'] ?? ''));
        $codesWereSent = $regionCode !== '' || $communeCode !== '';

        if (! $addressWasSent && ! $codesWereSent) {
            return null;
        }

        $address = $addressWasSent
            ? trim((string) ($data['address'] ?? ''))
            : trim((string) ($existing?->address_line ?? ''));

        if ($address === '') {
            if ($codesWereSent) {
                throw ValidationException::withMessages([
                    'address' => ['Ingresa la calle y número para guardar la dirección.'],
                ]);
            }

            return [];
        }

        $location = $codesWereSent
            ? $this->territorialCatalog->resolve($regionCode, $communeCode)
            : null;

        return [
            'address_line' => $address,
            'region' => $location['region'] ?? $existing?->region,
            'commune' => $location['commune'] ?? $existing?->commune,
        ];
    }

    private function ensureUniqueEmail(string $email, ?int $exceptId = null): void
    {
        if (Patient::withTrashed()->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => ['Ya existe una ficha con este correo en el centro.']]);
        }
    }

    private function present(Patient $patient): array
    {
        return [
            'id' => $patient->id, 'first_name' => $patient->first_name,
            'last_name' => $patient->last_name, 'rut' => $patient->rut,
            'birth_date' => $patient->birth_date?->toDateString(),
            'email' => $patient->email, 'phone' => $patient->phone,
            'health_insurance' => $patient->healthInsurance?->name,
            'medical_insurance' => $patient->medical_insurance,
            'address' => $patient->primaryAddress?->address_line,
            'region' => $patient->primaryAddress?->region,
            'commune' => $patient->primaryAddress?->commune,
            'consent' => $patient->consent_at !== null,
            'is_active' => $patient->is_active, 'has_account' => $patient->user_id !== null,
        ];
    }
}
