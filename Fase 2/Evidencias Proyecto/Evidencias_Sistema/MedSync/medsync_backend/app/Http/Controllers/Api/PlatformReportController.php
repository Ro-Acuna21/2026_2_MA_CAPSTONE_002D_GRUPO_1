<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        // Core records account-to-center links. Clinical records never leave their tenant.
        $patientLinks = CenterUser::query()
            ->where('role', 'PACIENTE')
            ->where('is_active', true)
            ->whereNotNull('patient_id')
            ->whereHas('user', fn ($query) => $query->where('is_active', true));

        $linksByCenter = (clone $patientLinks)
            ->selectRaw('medical_center_id, COUNT(DISTINCT user_id) as patient_count')
            ->groupBy('medical_center_id')
            ->pluck('patient_count', 'medical_center_id');
        $linksByUser = (clone $patientLinks)
            ->selectRaw('user_id, COUNT(DISTINCT medical_center_id) as center_count')
            ->groupBy('user_id')
            ->pluck('center_count', 'user_id');
        $centers = MedicalCenter::query()->orderBy('name')->get();

        return response()->json(['data' => [
            'total_centers' => $centers->count(),
            'active_centers' => $centers->where('is_active', true)->count(),
            'total_platform_users' => User::query()->count(),
            'unique_patients' => $linksByUser->count(),
            'patient_center_registrations' => $linksByCenter->sum(),
            'patients_in_multiple_centers' => $linksByUser->filter(fn ($count) => $count > 1)->count(),
            'users_without_center' => User::query()->where(fn ($query) => $query
                ->whereNull('system_role')->orWhere('system_role', '!=', 'SUPER_ADMIN'))
                ->whereDoesntHave('centerUsers')->count(),
            'centers' => $centers->map(fn (MedicalCenter $center) => [
                'id' => $center->id,
                'name' => $center->name,
                'slug' => $center->slug,
                'is_active' => $center->is_active,
                'patient_count' => (int) ($linksByCenter[$center->id] ?? 0),
            ])->values(),
        ]]);
    }
}
