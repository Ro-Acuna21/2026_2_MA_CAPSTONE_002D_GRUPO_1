<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Core\MedicalCenter;
use App\Rules\ValidRut;
use App\Services\Tenants\CenterProvisioner;
use App\Services\TerritorialCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PlatformCenterController extends Controller
{
    public function __construct(private readonly CenterProvisioner $provisioner, private readonly TerritorialCatalog $territorial) {}

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
            'public_description' => ['nullable', 'string', 'max:2000'],
            'address_line' => ['nullable', 'string', 'max:200'],
            'region_code' => ['nullable', 'string', 'regex:/^\d{2}$/'],
            'commune_code' => ['nullable', 'string', 'regex:/^\d{5}$/'],
            'is_active' => ['required', 'boolean'],
        ]);

        $address = $this->addressData($data);
        $activate = (bool) $data['is_active'];
        $center = MedicalCenter::create([
            ...collect($data)->except(['address_line', 'region_code', 'commune_code'])->all(),
            'database_name' => 'medsync_c_'.Str::lower((string) Str::ulid()),
            'provisioning_status' => 'PENDING',
            'is_active' => false,
        ]);

        $this->saveAddress($center, $address);

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
            'public_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'address_line' => ['sometimes', 'nullable', 'string', 'max:200'],
            'region_code' => ['sometimes', 'nullable', 'string', 'regex:/^\d{2}$/'],
            'commune_code' => ['sometimes', 'nullable', 'string', 'regex:/^\d{5}$/'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (($data['is_active'] ?? false) && $center->provisioning_status !== 'READY') {
            throw ValidationException::withMessages(['is_active' => ['El centro aún no tiene una base clínica lista.']]);
        }
        $addressChanged = array_key_exists('address_line', $data)
            || array_key_exists('region_code', $data) || array_key_exists('commune_code', $data);
        $address = $addressChanged ? $this->addressData($data) : null;
        $center->update(collect($data)->except(['address_line', 'region_code', 'commune_code'])->all());
        if ($addressChanged) $this->saveAddress($center, $address);

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
        $center->loadMissing('currentSubscription.plan', 'primaryAddress');
        return [
            ...$center->only(['id', 'name', 'slug', 'rut', 'phone', 'email', 'public_description', 'is_active', 'provisioning_status', 'provisioned_at']),
            'address_line' => $center->primaryAddress?->address_line,
            'commune' => $center->primaryAddress?->commune,
            'region' => $center->primaryAddress?->region,
            'plan' => $center->currentSubscription?->plan?->code,
            'subscription_status' => $center->currentSubscription?->status,
            'subscription_renews_at' => $center->currentSubscription?->renews_at?->toDateString(),
        ];
    }

    private function addressData(array $data): ?array
    {
        $line = trim((string) ($data['address_line'] ?? ''));
        $regionCode = trim((string) ($data['region_code'] ?? ''));
        $communeCode = trim((string) ($data['commune_code'] ?? ''));
        if ($line === '' && $regionCode === '' && $communeCode === '') return null;
        if ($line === '') {
            throw ValidationException::withMessages(['address_line' => ['Ingresa la dirección para guardar la región y comuna.']]);
        }
        $location = $this->territorial->resolve($regionCode, $communeCode);
        return ['address_line' => $line, ...$location];
    }

    private function saveAddress(MedicalCenter $center, ?array $address): void
    {
        if ($address === null) {
            $center->primaryAddress()->delete();
            return;
        }
        $center->primaryAddress()->updateOrCreate(
            ['medical_center_id' => $center->id, 'is_primary' => true],
            $address
        );
    }
}
