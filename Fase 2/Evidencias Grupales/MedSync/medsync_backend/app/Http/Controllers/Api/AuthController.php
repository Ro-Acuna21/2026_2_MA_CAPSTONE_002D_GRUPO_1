<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Core\CenterUser;
use App\Models\Center\HealthInsurance;
use App\Models\Core\MedicalCenter;
use App\Models\Center\Patient;
use App\Models\User;
use App\Rules\ValidRut;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\Center\PatientAddress;
use App\Services\Tenants\TenantConnectionResolver;
use App\Support\TenantContext;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly TenantConnectionResolver $connectionResolver,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * POST /api/register
     *
     * Iteración 1: un solo centro médico fijo ("clinica-horizonte").
     * Autenticación por sesión/cookies de Sanctum (no token Bearer), tal
     * como espera el frontend (credentials: 'include' + csrf-cookie).
     */
    public function register(Request $request)
    {
        // Normaliza el correo y el teléfono antes de validar.
        // El correo se almacena en minúsculas y sin espacios exteriores.
        // Normaliza el teléfono antes de validar, igual que hace el frontend
        // (quita espacios, paréntesis y guiones) para que el regex sea comparable.
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
            'phone' => preg_replace('/[\s()-]/', '', (string) $request->input('phone')),
        ]);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'rut' => ['required', 'string', new ValidRut()],
            'birth_date' => ['required', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^(?:\+?56)?[2-9]\d{8}$/'],
            'health_insurance' => ['required', Rule::in(['Fonasa', 'Isapre', 'Particular', 'Otra'])],
            'medical_insurance' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:200'],
            'password' => ['required', 'string', 'min:8', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/\d/', 'confirmed'],
            'consent' => ['required', 'accepted'],
        ]);
        $existingUser = User::query()
    ->whereRaw('LOWER(TRIM(email)) = ?', [$data['email']])
    ->exists();

if ($existingUser) {
    throw ValidationException::withMessages([
        'email' => [
            'Ya existe una cuenta registrada con este correo electrónico.',
        ],
    ]);
}

        $rut = ValidRut::normalize($data['rut']);

        $medicalCenter = MedicalCenter::where('slug', 'clinica-horizonte')->first();

        if (! $medicalCenter) {
            throw ValidationException::withMessages([
                'email' => ['El centro médico no está configurado. Ejecuta el seeder de MedicalCenterSeeder.'],
            ]);
        }

        $healthInsurance = HealthInsurance::where('name', $data['health_insurance'])->first();

        if (! $healthInsurance) {
            throw ValidationException::withMessages([
                'health_insurance' => ['Previsión no reconocida. Ejecuta el seeder de previsiones.'],
            ]);
        }

        $patient = DB::transaction(function () use ($data, $rut, $medicalCenter, $healthInsurance) {
            $existingPatient = Patient::where(function ($query) use ($rut, $data) {
    $query->where('rut', $rut)
        ->orWhereRaw(
            'LOWER(TRIM(email)) = ?',
            [$data['email']]
        );
})
->first();

            $rutMatches = $existingPatient && $existingPatient->rut === $rut;
            $emailMatches = $existingPatient
    && strtolower(trim($existingPatient->email)) === $data['email'];

            if ($existingPatient && $existingPatient->user_id) {
                throw ValidationException::withMessages([
                    'email' => ['Ya existe una cuenta registrada con ese RUT o correo en este centro.'],
                ]);
            }

            if ($existingPatient && ! ($rutMatches && $emailMatches)) {
                // Solo coincide uno de los dos datos: recepción debe corregirlo primero.
                throw ValidationException::withMessages([
                    'rut' => ['El RUT o el correo ya están asociados a otra ficha de este centro. Contacta a recepción para corregir tus datos.'],
                ]);
            }

            $user = User::create([
                'name' => trim($data['first_name'].' '.$data['last_name']),
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
            ]);

            if ($existingPatient) {
                $existingPatient->update(['user_id' => $user->id]);
                $patient = $existingPatient;
            } else {
                $patient = Patient::create([
                    'user_id' => $user->id,
                    'health_insurance_id' => $healthInsurance->id,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'rut' => $rut,
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'birth_date' => $data['birth_date'],
                    'medical_insurance' => $data['medical_insurance'] ?? null,
                    'consent_at' => now(),
                    'consent_version' => 'v1',
                    'is_active' => true,
                ]);
            }
            if (! empty($data['address'])) {
    PatientAddress::updateOrCreate(
        [
            'patient_id' => $patient->id,
            'is_primary' => true,
        ],
        [
            'address_line' => $data['address'],
        ]
    );
}

            CenterUser::firstOrCreate(
                [
                    'medical_center_id' => $medicalCenter->id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => 'PACIENTE',
                    'patient_id' => $patient->id,
                    'is_active' => true,
                ]
            );

            return $patient;
        });

        return response()->json([
            'data' => $this->presentUser($patient->user()->first()),
        ], 201);
    }

    /**
     * POST /api/login
     */
    public function login(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
            'center_slug' => trim((string) $request->input('center_slug')) ?: null,
        ]);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'center_slug' => ['nullable', 'string', 'max:150'],
        ]);

        $medicalCenter = null;

        if ($data['center_slug'] ?? null) {
            $medicalCenter = MedicalCenter::query()
                ->where('slug', $data['center_slug'])
                ->where('is_active', true)
                ->first();

            if (! $medicalCenter) {
                throw ValidationException::withMessages([
                    'center_slug' => ['El centro médico solicitado no está disponible.'],
                ]);
            }
        }

        if (! Auth::guard('web')->attempt([
            'email' => $data['email'],
            'password' => $data['password'],
        ])) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales ingresadas no son correctas.'],
            ]);
        }

        $user = Auth::guard('web')->user();

        if (! $user->is_active) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => ['Esta cuenta se encuentra inactiva.'],
            ]);
        }

        if (! $medicalCenter) {
            if (! $user->isSuperAdmin()) {
                Auth::guard('web')->logout();

                throw ValidationException::withMessages([
                    'center_slug' => ['Selecciona el portal del centro médico al que perteneces.'],
                ]);
            }

            if ($request->hasSession()) {
                $request->session()->regenerate();
                $request->session()->forget('active_medical_center_id');
            }

            return response()->json([
                'data' => $this->presentUser($user),
            ]);
        }

        if ($user->isSuperAdmin()) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'center_slug' => ['Las cuentas de plataforma deben ingresar desde el acceso de plataforma.'],
            ]);
        }

        $centerUser = $user->centerUsers()
            ->where('medical_center_id', $medicalCenter->id)
            ->where('is_active', true)
            ->with('medicalCenter')
            ->first();

        if (! $centerUser) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'center_slug' => ['Tu cuenta no tiene acceso activo a este centro médico.'],
            ]);
        }

        $this->connectionResolver->connect($medicalCenter);
        $centerUser->load(['patient', 'professional']);

        if ($request->hasSession()) {
            $request->session()->regenerate();
            $request->session()->put('active_medical_center_id', $medicalCenter->id);
        }

        return response()->json([
            'data' => $this->presentUser($user, $centerUser),
        ]);
    }

    /**
     * POST /api/logout
     */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    /**
     * GET /api/v1/me
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return response()->json([
                'data' => $this->presentUser($user),
            ]);
        }

        return response()->json([
            'data' => $this->presentUser($user, $this->tenantContext->centerUser(), false),
        ]);
    }

    private function presentUser(User $user, ?CenterUser $centerUser = null, bool $fallbackToFirstMembership = true): array
    {
        if (! $user->isSuperAdmin() && ! $centerUser && $fallbackToFirstMembership) {
            $centerUser = $user->centerUsers()
                ->with(['patient', 'professional', 'medicalCenter'])
                ->where('is_active', true)
                ->first();
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->system_role === 'SUPER_ADMIN'
            ? 'SUPER_ADMIN'
            : $centerUser?->role,
            'medical_center' => $centerUser?->medicalCenter,
            'patient' => $centerUser?->patient,
            'professional' => $centerUser?->professional,
        ];
    }
}
