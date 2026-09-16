<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Center\Professional;
use App\Rules\ValidRut;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProfessionalController extends Controller
{
    /**
     * GET /api/v1/professionals
     *
     * Obtiene los profesionales reales de la base de datos del centro.
     */
    public function index(Request $request)
    {
        $this->ensureAdmin($request);

        $professionals = Professional::query()
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
        $this->ensureAdmin($request);

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

        $professional = Professional::create([
            'user_id' => null,

            'first_name' => trim($data['first_name']),

            'last_name' => trim($data['last_name']),

            'rut' => $rut,

            'email' => $email,

            'phone' => $data['phone'],

            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'data' => $this->presentProfessional($professional),
        ], 201);
    }

    /**
     * Verifica que el usuario autenticado sea
     * administrador de un centro médico.
     */
    private function ensureAdmin(Request $request): void
    {
        $centerUser = $request->user()
            ->centerUsers()
            ->where('role', 'ADMIN')
            ->where('is_active', true)
            ->first();

        abort_unless(
            $centerUser,
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
        ];
    }
}