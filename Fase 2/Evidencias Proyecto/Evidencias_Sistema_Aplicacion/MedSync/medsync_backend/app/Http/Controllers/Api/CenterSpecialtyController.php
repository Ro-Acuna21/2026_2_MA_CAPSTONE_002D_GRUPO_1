<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Core\MedicalCenterSpecialty;
use App\Models\Core\Specialty;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Selección del centro dentro del catálogo global de especialidades
 * (medsync_core.medical_center_specialties).
 *
 * El administrador del centro solo activa o desactiva su propia fila;
 * nunca crea ni edita el catálogo global (eso es PlatformSpecialtyController,
 * exclusivo de SUPER_ADMIN). Prestaciones y profesionales usan estos IDs
 * globales como referencias lógicas en la base operacional del centro.
 */
class CenterSpecialtyController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {
    }

    /**
     * GET /api/v1/center/specialties
     *
     * Muestra el catálogo global completo (activo e inactivo) junto con
     * la selección propia del centro, sin revelar la de otros centros.
     */
    public function index(Request $request)
    {
        $this->ensureAdmin();

        $medicalCenterId = $this->tenantContext->medicalCenter()->id;

        $selections = MedicalCenterSpecialty::query()
            ->where('medical_center_id', $medicalCenterId)
            ->get()
            ->keyBy('specialty_id');

        $specialties = Specialty::query()
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $specialties->map(
                fn (Specialty $specialty) => $this->present($specialty, $selections->get($specialty->id))
            ),
        ]);
    }

    /**
     * PUT /api/v1/center/specialties/{specialty}
     *
     * {specialty} es el ID del catálogo global. Crea o actualiza
     * únicamente la fila de medical_center_specialties del centro
     * resuelto desde sesión; el cliente nunca envía medical_center_id.
     */
    public function update(Request $request, int $specialty)
    {
        $this->ensureAdmin();

        $globalSpecialty = Specialty::findOrFail($specialty);

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        if ($data['is_active'] && ! $globalSpecialty->is_active) {
            throw ValidationException::withMessages([
                'is_active' => ['No se puede activar una especialidad que está inactiva en el catálogo global.'],
            ]);
        }

        $medicalCenterId = $this->tenantContext->medicalCenter()->id;

        $selection = MedicalCenterSpecialty::query()->updateOrCreate(
            [
                'medical_center_id' => $medicalCenterId,
                'specialty_id' => $globalSpecialty->id,
            ],
            [
                'is_active' => $data['is_active'],
            ]
        );

        return response()->json([
            'data' => $this->present($globalSpecialty->fresh(), $selection),
        ]);
    }

    /**
     * Verifica que el usuario autenticado sea administrador del centro
     * resuelto (mismo patrón que SpecialtyController::ensureAdmin).
     */
    private function ensureAdmin(): void
    {
        $centerUser = $this->tenantContext->centerUser();

        abort_unless(
            auth()->user()?->can('center.specialties.manage'),
            403,
            'No tienes permisos para administrar las especialidades del centro.'
        );
    }

    private function present(Specialty $specialty, ?MedicalCenterSpecialty $selection): array
    {
        return [
            'id' => $specialty->id,
            'name' => $specialty->name,
            'description' => $specialty->description,
            'is_active' => $specialty->is_active,
            'center_is_active' => $selection?->is_active,
        ];
    }
}
