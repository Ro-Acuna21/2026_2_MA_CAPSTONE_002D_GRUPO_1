<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Services\Authorization\CenterAccessService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CenterAccessController extends Controller
{
    public function __construct(private readonly CenterAccessService $access, private readonly TenantContext $tenant) {}

    public function platformIndex(Request $request, MedicalCenter $center): JsonResponse
    {
        $this->authorizePlatform($request);
        return response()->json(['data' => $this->access->list($center, 'ADMIN')]);
    }

    public function platformStore(Request $request, MedicalCenter $center): JsonResponse
    {
        $this->authorizePlatform($request);
        return $this->store($request, $center, 'ADMIN');
    }

    public function platformUpdate(Request $request, MedicalCenter $center, CenterUser $membership): JsonResponse
    {
        $this->authorizePlatform($request);
        return $this->update($request, $center, $membership, 'ADMIN');
    }

    public function platformResend(Request $request, MedicalCenter $center, CenterUser $membership): JsonResponse
    {
        $this->authorizePlatform($request);
        return $this->resend($center, $membership, 'ADMIN');
    }

    public function platformStaff(Request $request, MedicalCenter $center): JsonResponse
    {
        $this->authorizePlatform($request);
        return response()->json(['data' => $this->access->staff($center)]);
    }

    public function platformChangeStaffRole(Request $request, MedicalCenter $center, CenterUser $membership): JsonResponse
    {
        $this->authorizePlatform($request);
        $data = $request->validate(['role' => ['required', Rule::in(['ADMIN', 'RECEPCIONISTA'])]]);
        return response()->json(['data' => $this->access->changeStaffRole($center, $membership, $data['role'])]);
    }

    public function centerIndex(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->access->list($this->authorizeCenterAdmin($request), 'RECEPCIONISTA')]);
    }

    public function centerStore(Request $request): JsonResponse
    {
        return $this->store($request, $this->authorizeCenterAdmin($request), 'RECEPCIONISTA');
    }

    public function centerUpdate(Request $request, CenterUser $membership): JsonResponse
    {
        return $this->update($request, $this->authorizeCenterAdmin($request), $membership, 'RECEPCIONISTA');
    }

    public function centerResend(Request $request, CenterUser $membership): JsonResponse
    {
        return $this->resend($this->authorizeCenterAdmin($request), $membership, 'RECEPCIONISTA');
    }

    private function store(Request $request, MedicalCenter $center, string $role): JsonResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
        ]);
        return response()->json($this->access->assign($center, $role, $data['email'], $data['name']), 201);
    }

    private function update(Request $request, MedicalCenter $center, CenterUser $membership, string $role): JsonResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        return response()->json(['data' => $this->access->setActive($center, $membership, $role, (bool) $data['is_active'])]);
    }

    private function resend(MedicalCenter $center, CenterUser $membership, string $role): JsonResponse
    {
        $result = $this->access->resend($center, $membership, $role);
        return response()->json($result, $result['invitation_sent'] ? 200 : 503);
    }

    private function authorizePlatform(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
    }

    private function authorizeCenterAdmin(Request $request): MedicalCenter
    {
        abort_unless(! $request->user()?->isSuperAdmin() && $this->tenant->centerUser()->role === 'ADMIN', 403);
        return $this->tenant->medicalCenter();
    }
}
