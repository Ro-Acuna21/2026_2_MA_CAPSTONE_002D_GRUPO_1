<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Center\HealthInsurance;
use App\Models\Center\Patient;
use App\Models\Center\PatientAddress;
use App\Rules\ValidRut;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PatientController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function index()
    {
        $this->requireReception();

        return response()->json(['data' => Patient::query()
            ->with(['healthInsurance', 'primaryAddress'])
            ->where('is_active', true)->orderBy('first_name')->orderBy('last_name')->get()
            ->map(fn (Patient $patient) => $this->present($patient))]);
    }

    public function show(int $patient)
    {
        $this->authorizePatient($patient);

        return response()->json(['data' => $this->present($this->findPatient($patient))]);
    }

    public function store(Request $request)
    {
        $this->requireReception();
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
            'consent' => ['required', 'accepted'],
        ]);
        $rut = ValidRut::normalize($data['rut']);
        if (Patient::withTrashed()->where('rut', $rut)->exists()) {
            throw ValidationException::withMessages(['rut' => ['Ya existe una ficha con este RUT en el centro.']]);
        }
        $this->ensureUniqueEmail($data['email']);
        $insurance = $this->insurance($data['health_insurance']);

        $patient = DB::connection('center')->transaction(function () use ($data, $rut, $insurance) {
            $patient = Patient::create([
                'first_name' => trim($data['first_name']), 'last_name' => trim($data['last_name']),
                'rut' => $rut, 'birth_date' => $data['birth_date'], 'email' => $data['email'],
                'phone' => $data['phone'], 'health_insurance_id' => $insurance->id,
                'medical_insurance' => $data['medical_insurance'] ?? null,
                'consent_at' => now(), 'consent_version' => 'v1', 'is_active' => true,
            ]);
            if (! empty($data['address'])) {
                PatientAddress::create(['patient_id' => $patient->id, 'address_line' => $data['address'], 'is_primary' => true]);
            }
            return $patient;
        });

        return response()->json(['data' => $this->present($patient->load(['healthInsurance', 'primaryAddress']))], 201);
    }

    public function update(Request $request, int $patient)
    {
        $this->authorizePatient($patient);
        $record = $this->findPatient($patient);
        $isReception = $this->tenantContext->centerUser()->role === 'RECEPCIONISTA';
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

        DB::connection('center')->transaction(function () use ($record, $data) {
            $record->update(collect($data)->except(['address', 'health_insurance'])->all());
            if (array_key_exists('address', $data)) {
                if ($data['address']) {
                    PatientAddress::updateOrCreate(
                        ['patient_id' => $record->id, 'is_primary' => true],
                        ['address_line' => $data['address']],
                    );
                } else {
                    $record->primaryAddress()->delete();
                }
            }
        });

        return response()->json(['data' => $this->present($record->fresh(['healthInsurance', 'primaryAddress']))]);
    }

    private function requireReception(): void
    {
        abort_unless($this->tenantContext->hasCenter() && $this->tenantContext->centerUser()->role === 'RECEPCIONISTA', 403);
    }

    private function authorizePatient(int $patient): void
    {
        abort_unless($this->tenantContext->hasCenter(), 403);
        $membership = $this->tenantContext->centerUser();
        abort_unless($membership->role === 'RECEPCIONISTA' || ($membership->role === 'PACIENTE' && (int) $membership->patient_id === $patient), 403);
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
            'consent' => $patient->consent_at !== null,
            'is_active' => $patient->is_active, 'has_account' => $patient->user_id !== null,
        ];
    }
}
