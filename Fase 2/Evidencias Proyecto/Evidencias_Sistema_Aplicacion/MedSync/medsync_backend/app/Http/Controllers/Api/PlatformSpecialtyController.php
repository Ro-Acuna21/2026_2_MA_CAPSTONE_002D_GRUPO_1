<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Core\Specialty;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo global de especialidades (medsync_core.specialties).
 *
 * Exclusivo de SUPER_ADMIN. El administrador de un centro no puede crear
 * ni renombrar especialidades globales; solo activa/desactiva su propia
 * selección mediante CenterSpecialtyController.
 *
 * Contrato: medsync_frontend/docs/api-contract.md
 * ("Propuesta para backend; ninguna de estas rutas existe todavía").
 */
class PlatformSpecialtyController extends Controller
{
    /**
     * GET /api/v1/platform/specialties
     */
    public function index(Request $request)
    {
        $this->ensureSuperAdmin($request);

        $specialties = Specialty::query()
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $specialties->map(
                fn (Specialty $specialty) => $this->present($specialty)
            ),
        ]);
    }

    /**
     * POST /api/v1/platform/specialties
     */
    public function store(Request $request)
    {
        $this->ensureSuperAdmin($request);

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
            'data' => $this->present($specialty),
        ], 201);
    }

    /**
     * PATCH /api/v1/platform/specialties/{specialty}
     *
     * Desactivar globalmente no borra la selección de los centros ni su
     * historial clínico: solo impide que un centro la active desde ahora
     * (ver CenterSpecialtyController::ensureGloballyActive).
     */
    public function update(Request $request, int $specialty)
    {
        $this->ensureSuperAdmin($request);

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
            'data' => $this->present($record->fresh()),
        ]);
    }

    /**
     * Replica en PHP la restricción única de BD
     * (specialties_name_unique_ci) sobre el catálogo global.
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
                'name' => ['Ya existe una especialidad con ese nombre en el catálogo global.'],
            ]);
        }
    }

    private function ensureSuperAdmin(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && $user->isSuperAdmin(), 403, 'No tienes permisos para administrar el catálogo global.');
    }

    private function present(Specialty $specialty): array
    {
        return [
            'id' => $specialty->id,
            'name' => $specialty->name,
            'description' => $specialty->description,
            'is_active' => $specialty->is_active,
        ];
    }
}
