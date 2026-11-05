<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Authorization\CenterRolePermissionSync;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CenterPermissionCatalogController extends Controller
{
    public function __invoke(Request $request, TenantContext $tenant): JsonResponse
    {
        abort_unless($tenant->centerUser()->role === 'ADMIN' &&
            $request->user()?->can('professionals.access.manage'), 403);

        return response()->json(['data' => [
            'source' => 'fixed_backend_catalog',
            'roles' => CenterRolePermissionSync::ROLE_PERMISSIONS,
        ]]);
    }
}
