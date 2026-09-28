<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Center\Professional;
use App\Models\Core\CenterUser;
use App\Services\Professionals\ProfessionalAccessService;
use Illuminate\Http\Request;

class ProfessionalAccessController extends Controller
{
    public function __construct(
        private readonly ProfessionalAccessService $professionalAccessService
    ) {
    }

    /**
     * Habilita acceso a MedSync para un profesional.
     */
    public function enable(
        Request $request,
        Professional $professional
    ) {
        $adminCenterUser = $this->getAdminCenterUser($request);

        $result = $this->professionalAccessService->enableAccess(
            $professional,
            $adminCenterUser
        );

        return response()->json([
            'message' => $result['message'],
            'data' => [
                'professional_id' => $result['professional']->id,
                'user_id' => $result['user']->id,
                'role' => $result['center_user']->role,
                'account_created' => $result['account_created'],
                'invitation_sent' => $result['invitation_sent'],
            ],
        ]);
    }

    /**
     * Genera una nueva invitación para una cuenta profesional.
     */
    public function resendInvitation(
        Request $request,
        Professional $professional
    ) {
        $adminCenterUser = $this->getAdminCenterUser($request);

        $result = $this->professionalAccessService->resendInvitation(
            $professional,
            $adminCenterUser
        );

        return response()->json($result);
    }

    /**
     * Obtiene la membresía ADMIN del usuario autenticado.
     */
    private function getAdminCenterUser(Request $request): CenterUser
    {
        $centerUser = $request->user()
            ->centerUsers()
            ->with('medicalCenter')
            ->where('role', 'ADMIN')
            ->where('is_active', true)
            ->first();

        abort_unless(
            $centerUser,
            403,
            'No tienes permisos para gestionar el acceso de profesionales.'
        );

        return $centerUser;
    }
}