<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Core\MedicalCenter;
use App\Rules\ValidRut;
use App\Services\Tenants\CenterProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PlatformCenterController extends Controller
{
    public function __construct(private readonly CenterProvisioner $provisioner) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizePlatform($request);

        return response()->json(['data' => MedicalCenter::query()->orderBy('name')->get()->map(
            fn (MedicalCenter $center) => $this->present($center)
        )]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePlatform($request);
        $request->merge(['rut' => ValidRut::normalize((string) $request->input('rut'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'slug' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('core.medical_centers', 'slug')],
            'rut' => ['required', 'string', new ValidRut(), Rule::unique('core.medical_centers', 'rut')],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['required', 'boolean'],
        ]);

        $activate = (bool) $data['is_active'];
        $center = MedicalCenter::create([
            ...$data,
            'database_name' => 'medsync_c_'.Str::lower((string) Str::ulid()),
            'provisioning_status' => 'PENDING',
            'is_active' => false,
        ]);

        return $this->provision($center, $activate, 201);
    }

    public function update(Request $request, MedicalCenter $center): JsonResponse
    {
        $this->authorizePlatform($request);
        if ($request->exists('rut')) {
            $request->merge(['rut' => ValidRut::normalize((string) $request->input('rut'))]);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:3', 'max:150'],
            'slug' => ['sometimes', 'required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('core.medical_centers', 'slug')->ignore($center->id)],
            'rut' => ['sometimes', 'required', 'string', new ValidRut(), Rule::unique('core.medical_centers', 'rut')->ignore($center->id)],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (($data['is_active'] ?? false) && $center->provisioning_status !== 'READY') {
            throw ValidationException::withMessages(['is_active' => ['El centro aún no tiene una base clínica lista.']]);
        }
        $center->update($data);

        return response()->json(['data' => $this->present($center->fresh())]);
    }

    public function retry(Request $request, MedicalCenter $center): JsonResponse
    {
        $this->authorizePlatform($request);
        if ($center->provisioning_status === 'READY') {
            return response()->json(['data' => $this->present($center)]);
        }
        $data = $request->validate(['is_active' => ['sometimes', 'boolean']]);

        return $this->provision($center, (bool) ($data['is_active'] ?? true), 200);
    }

    private function provision(MedicalCenter $center, bool $activate, int $successStatus): JsonResponse
    {
        try {
            $this->provisioner->provision($center, $activate);
        } catch (Throwable $exception) {
            report($exception);
            return response()->json([
                'message' => 'No fue posible preparar la base clínica. El centro permanece inactivo; puedes reintentar.',
                'data' => $this->present($center->fresh()),
            ], 503);
        }

        return response()->json(['data' => $this->present($center->fresh())], $successStatus);
    }

    private function authorizePlatform(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
    }

    private function present(MedicalCenter $center): array
    {
        $center->loadMissing('currentSubscription.plan');
        return [
            ...$center->only(['id', 'name', 'slug', 'rut', 'phone', 'email', 'is_active', 'provisioning_status', 'provisioned_at']),
            'plan' => $center->currentSubscription?->plan?->code,
            'subscription_status' => $center->currentSubscription?->status,
            'subscription_renews_at' => $center->currentSubscription?->renews_at?->toDateString(),
        ];
    }
}
