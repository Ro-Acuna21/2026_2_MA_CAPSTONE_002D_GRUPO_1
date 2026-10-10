<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Center\Professional;
use App\Models\Center\Specialty;
use App\Rules\ValidRut;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfessionalController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    /**
     * GET /api/v1/professionals
     *
     * Obtiene los profesionales reales de la base de datos del centro.
     */
    public function index(Request $request)
    {
        $this->ensureAdmin();

        $professionals = Professional::query()
            ->with('specialties:id,name')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return response()->json([
            'data' => $professionals->map(
                fn (Professional $professional) =>
                    $this->presentProfessional($professional)
            ),
        ]);
    }

    /**
     * POST /api/v1/professionals
     *
     * Crea una ficha de profesional.
     *
     * Por ahora no crea una cuenta para iniciar sesión,
     * por lo tanto user_id queda en NULL.
     */
    public function store(Request $request)
    {
        $this->ensureAdmin();

        // Normaliza el teléfono antes de validarlo.
        $request->merge([
            'phone' => preg_replace(
                '/[\s()-]/',
                '',
                (string) $request->input('phone')
            ),
        ]);

        $data = $request->validate([
            'first_name' => [
                'required',
                'string',
                'max:100',
            ],

            'last_name' => [
                'required',
                'string',
                'max:100',
            ],

            'rut' => [
                'required',
                'string',
                new ValidRut(),
            ],

            'email' => [
                'required',
                'email',
                'max:150',
            ],

            'phone' => [
                'required',
                'string',
                'regex:/^(?:\+?56)?[2-9]\d{8}$/',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'specialty_ids' => ['sometimes', 'array'],
            'specialty_ids.*' => ['integer', 'distinct', Rule::exists('center.specialties', 'id')->where('is_active', true)],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $rut = ValidRut::normalize($data['rut']);

        $email = strtolower(trim($data['email']));

        // Revisamos que el RUT o correo no estén ocupados
        // en este centro.
        $existingProfessional = Professional::query()
            ->where(function ($query) use ($rut, $email) {
                $query
                    ->where('rut', $rut)
                    ->orWhere('email', $email);
            })
            ->first();

        if ($existingProfessional) {
            throw ValidationException::withMessages([
                'rut' => [
                    'Ya existe un profesional con ese RUT o correo en este centro.',
                ],
            ]);
        }

        $professional = DB::connection('center')->transaction(function () use ($data, $rut, $email) {
            $professional = Professional::create([
            'user_id' => null,

            'first_name' => trim($data['first_name']),

            'last_name' => trim($data['last_name']),

            'rut' => $rut,

            'email' => $email,

            'phone' => $data['phone'],

                'is_active' => $data['is_active'] ?? true,
                'description' => $data['description'] ?? null,
            ]);

            if (array_key_exists('specialty_ids', $data)) {
                $professional->specialties()->sync($data['specialty_ids']);
            }

            return $professional->load('specialties:id,name');
        });

        return response()->json([
            'data' => $this->presentProfessional($professional),
        ], 201);
    }

    /**
     * PATCH /api/v1/professionals/{professional}
     * Actualiza la ficha del centro y la relación con especialidades.
     * La cuenta global y la membresía del centro no se modifican.
     */
    public function update(Request $request, int $professional)
    {
        $this->ensureAdmin();
        $record = Professional::with('specialties:id,name')->findOrFail($professional);

        $normalized = [];
        if ($request->has('email')) {
            $normalized['email'] = strtolower(trim((string) $request->input('email')));
        }
        if ($request->has('phone')) {
            $normalized['phone'] = preg_replace('/[\s()-]/', '', (string) $request->input('phone'));
        }
        $request->merge($normalized);

        $data = $request->validate([
            'first_name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:150'],
            'phone' => ['sometimes', 'required', 'string', 'regex:/^(?:\+?56)?[2-9]\d{8}$/'],
            'rut' => ['prohibited'],
            'user_id' => ['prohibited'],
            'specialty_ids' => ['sometimes', 'array'],
            'specialty_ids.*' => ['integer', 'distinct', Rule::exists('center.specialties', 'id')->where('is_active', true)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($record->user_id !== null) {
            if (array_key_exists('email', $data) && $data['email'] !== strtolower(trim($record->email))) {
                throw ValidationException::withMessages(['email' => ['El correo está vinculado a una cuenta. Para proteger el acceso, no se puede cambiar desde la ficha.']]);
            }
            if (array_key_exists('is_active', $data) && (bool) $data['is_active'] !== $record->is_active) {
                throw ValidationException::withMessages(['is_active' => ['El estado de un profesional con cuenta no se puede cambiar desde esta ficha.']]);
            }
        }

        if (array_key_exists('email', $data) && $data['email'] !== strtolower(trim($record->email))
            && Professional::withTrashed()->where('id', '<>', $record->id)->whereRaw('LOWER(TRIM(email)) = ?', [$data['email']])->exists()) {
            throw ValidationException::withMessages(['email' => ['Ya existe un profesional con ese correo en este centro.']]);
        }

        DB::connection('center')->transaction(function () use ($record, $data) {
            $record->update(collect($data)->except('specialty_ids')->all());
            if (array_key_exists('specialty_ids', $data)) {
                $record->specialties()->sync($data['specialty_ids']);
            }
        });

        return response()->json(['data' => $this->presentProfessional($record->fresh('specialties:id,name'))]);
    }

    /**
     * Verifica que el usuario autenticado sea
     * administrador de un centro médico.
     */
    private function ensureAdmin(): void
    {
        $centerUser = $this->tenantContext->centerUser();

        abort_unless(
            $centerUser->role === 'ADMIN',
            403,
            'No tienes permisos para gestionar profesionales.'
        );
    }

    /**
     * Define la estructura que Laravel devuelve al frontend.
     */
    private function presentProfessional(Professional $professional): array
    {
        return [
            'id' => $professional->id,

            'user_id' => $professional->user_id,

            'first_name' => $professional->first_name,

            'last_name' => $professional->last_name,

            'rut' => $professional->rut,

            'email' => $professional->email,

            'phone' => $professional->phone,

            'is_active' => $professional->is_active,

            'specialty_ids' => $professional->relationLoaded('specialties')
                ? $professional->specialties->pluck('id')->values()
                : [],

            'specialties' => $professional->relationLoaded('specialties')
                ? $professional->specialties->map(fn (Specialty $specialty) => ['id' => $specialty->id, 'name' => $specialty->name])->values()
                : [],

            'description' => $professional->description,
        ];
    }
}
