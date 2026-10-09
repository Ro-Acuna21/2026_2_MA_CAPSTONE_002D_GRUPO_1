<?php

namespace App\Http\Middleware;

use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Services\Tenants\TenantConnectionResolver;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCenterTenant
{
    public function __construct(
        private readonly TenantConnectionResolver $connectionResolver,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenantContext->reset();

        $user = $request->user();

        abort_unless($user, 401, 'Debes iniciar sesión.');

        // El acceso de plataforma no resuelve ni abre una base clínica.
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        abort_unless($request->hasSession(), 403, 'No existe un contexto de centro activo.');

        $medicalCenterId = $request->session()->get('active_medical_center_id');

        abort_unless($medicalCenterId, 403, 'No existe un contexto de centro activo.');

        $medicalCenter = MedicalCenter::query()
            ->whereKey($medicalCenterId)
            ->where('is_active', true)
            ->first();

        abort_unless($medicalCenter, 403, 'El centro médico no está disponible.');

        $centerUser = CenterUser::query()
            ->where('medical_center_id', $medicalCenter->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        abort_unless($centerUser, 403, 'Tu cuenta no tiene acceso activo a este centro médico.');

        $this->connectionResolver->connect($medicalCenter);
        $this->tenantContext->set($medicalCenter, $centerUser);

        return $next($request);
    }
}
