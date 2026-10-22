<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Center\Specialty;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SpecialtyController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {
    }

    /**
     * GET /api/v1/specialties
     *
     * Lista todas las especialidades del centro (activas e inactivas),
     * para la pantalla de administración. Solo el administrador del
     * centro puede ver las inactivas, por eso este listado completo
     * requiere ese rol.
     */
    public function index(Request $request)
    {
        $this->ensureAdmin();

        $specialties = Specialty::query()
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $specialties->map(
                fn (Specialty $specialty) => $this->presentSpecialty($specialty)
            ),
        ]);
    }

    /**
     * POST /api/v1/specialties
     *
     * Crea una especialidad dentro del centro activo.
     */
    public function store(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $name = trim($data['name']);

        $this->ensureNameAvailable($name);

        $specialty = Specialty::create([
            'name' => $name,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'data' => $this->presentSpecialty($specialty),
        ], 201);
    }

    /**
     * PATCH /api/v1/specialties/{specialty}
     *
     * Actualiza nombre, descripción o estado de una especialidad.
     * Desactivarla no elimina las prestaciones ya creadas bajo ella,
     * pero AppointmentService deja de ofrecerlas para reservar
     * (ver listActiveServices/findActiveService).
     */
    public function update(Request $request, int $specialty)
    {
        $this->ensureAdmin();

        $record = Specialty::findOrFail($specialty);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);
            $this->ensureNameAvailable($name, $record->id);
            $data['name'] = $name;
        }

        $record->update($data);

        return response()->json([
            'data' => $this->presentSpecialty($record->fresh()),
        ]);
    }

    /**
     * Replica en PHP la restricción única de BD (specialties_name_unique_ci):
     * dos especialidades del mismo centro no pueden compartir nombre
     * ignorando mayúsculas y espacios exteriores.
     */
    private function ensureNameAvailable(string $name, ?int $excludeId = null): void
    {
        $exists = Specialty::query()
            ->whereRaw('LOWER(BTRIM(name)) = ?', [mb_strtolower($name)])
            ->when(
                $excludeId,
                fn ($query) => $query->where('id', '!=', $excludeId)
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['Ya existe una especialidad con ese nombre en este centro.'],
            ]);
        }
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
            'No tienes permisos para gestionar especialidades.'
        );
    }

    private function presentSpecialty(Specialty $specialty): array
    {
        return [
            'id' => $specialty->id,
            'name' => $specialty->name,
            'description' => $specialty->description,
            'is_active' => $specialty->is_active,
        ];
    }
}
