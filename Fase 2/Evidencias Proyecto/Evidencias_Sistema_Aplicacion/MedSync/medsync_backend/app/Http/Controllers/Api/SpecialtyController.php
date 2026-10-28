<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Specialties\CenterSpecialtyCatalog;
use App\Support\TenantContext;
use Illuminate\Http\Request;

class SpecialtyController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly CenterSpecialtyCatalog $catalog
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
    public function index()
    {
        $this->ensureAdmin();

        $specialties = $this->catalog->selected();

        return response()->json([
            'data' => $specialties->map(
                fn ($specialty) => [
                    'id' => $specialty->id,
                    'name' => $specialty->name,
                    'description' => $specialty->description,
                    'is_active' => $specialty->center_is_active && $specialty->is_active,
                    'center_is_active' => $specialty->center_is_active,
                ]
            ),
        ]);
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
}
