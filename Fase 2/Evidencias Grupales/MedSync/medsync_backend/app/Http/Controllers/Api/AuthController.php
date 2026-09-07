<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CenterUser;
use App\Models\HealthInsurance;
use App\Models\MedicalCenter;
use App\Models\Patient;
use App\Models\User;
use App\Rules\ValidRut;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/register
     *
     * Iteración 1: un solo centro médico fijo ("clinica-horizonte").
     * Autenticación por sesión/cookies de Sanctum (no token Bearer), tal
     * como espera el frontend (credentials: 'include' + csrf-cookie).
     */
    public function register(Request $request)
    {
        // Normaliza el teléfono antes de validar, igual que hace el frontend
        // (quita espacios, paréntesis y guiones) para que el regex sea comparable.
        $request->merge([
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
            $existingPatient = Patient::where('medical_center_id', $medicalCenter->id)
                ->where(function ($query) use ($rut, $data) {
                    $query->where('rut', $rut)->orWhere('email', $data['email']);
                })
                ->first();

            $rutMatches = $existingPatient && $existingPatient->rut === $rut;
            $emailMatches = $existingPatient && $existingPatient->email === $data['email'];

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
                    'medical_center_id' => $medicalCenter->id,
                    'user_id' => $user->id,
                    'health_insurance_id' => $healthInsurance->id,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'rut' => $rut,
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'address' => $data['address'] ?? null,
                    'birth_date' => $data['birth_date'],
                    'medical_insurance' => $data['medical_insurance'] ?? null,
                    'consent_at' => now(),
                    'consent_version' => 'v1',
                    'is_active' => true,
                ]);
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
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales ingresadas no son correctas.'],
            ]);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => ['Esta cuenta se encuentra inactiva.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'data' => $this->presentUser($user),
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
        return response()->json([
            'data' => $this->presentUser($request->user()),
        ]);
    }

    private function presentUser(User $user): array
    {
        $centerUser = $user->centerUsers()
            ->with(['patient', 'professional', 'medicalCenter'])
            ->where('is_active', true)
            ->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $centerUser?->role,
            'medical_center' => $centerUser?->medicalCenter,
            'patient' => $centerUser?->patient,
            'professional' => $centerUser?->professional,
        ];
    }
}
