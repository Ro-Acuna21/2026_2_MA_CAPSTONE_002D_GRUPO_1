<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Center\Service;
use App\Models\Core\PlatformService;
use App\Models\Core\PlatformServicePrice;
use App\Services\Billing\EffectiveServicePrice;
use App\Support\TenantContext;
use App\Support\BusinessDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformServiceController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly EffectiveServicePrice $prices) {}

    public function index(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        return response()->json(['data' => PlatformService::query()->orderBy('category')->orderBy('name')->get()
            ->map(fn ($service) => $this->present($service))]);
    }

    public function centerIndex()
    {
        abort_unless($this->tenant->centerUser()->role === 'ADMIN', 403);
        $linked = Service::query()->whereNotNull('platform_service_id')->get()->keyBy('platform_service_id');
        return response()->json(['data' => PlatformService::query()->where('is_active', true)->orderBy('category')->orderBy('name')->get()
            ->map(function ($service) use ($linked) {
                $item = $this->present($service);
                $local = $linked->get($service->id);
                $item['center_service_id'] = $local?->id;
                $item['effective_price_clp'] = $local ? ($this->prices->forDate($local, BusinessDate::today())['amount_clp'] ?? null) : $item['base_price_clp'];
                return $item;
            })]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'in:CONSULTATION,EXAM,DIAGNOSIS'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
        ]);
        $data['name'] = trim($data['name']);
        if ($data['name'] === '') throw ValidationException::withMessages(['name' => 'El nombre es obligatorio.']);
        $this->ensureNameAvailable($data['name'], $data['category']);
        return response()->json(['data' => $this->present(PlatformService::create($data))], 201);
    }

    public function update(Request $request, PlatformService $service)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'category' => ['sometimes', 'in:CONSULTATION,EXAM,DIAGNOSIS'],
            'duration_minutes' => ['sometimes', 'integer', 'min:5', 'max:480'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['name'])) $data['name'] = trim($data['name']);
        if (isset($data['name']) && $data['name'] === '') throw ValidationException::withMessages(['name' => 'El nombre es obligatorio.']);
        if (isset($data['name']) || isset($data['category'])) {
            $this->ensureNameAvailable($data['name'] ?? $service->name, $data['category'] ?? $service->category, $service->id);
        }
        $service->update($data);
        return response()->json(['data' => $this->present($service->refresh())]);
    }

    public function price(Request $request, PlatformService $service)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $data = $request->validate([
            'amount_clp' => ['required', 'integer', 'min:0', 'max:999999999'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
        ]);
        if ($data['effective_from'] < BusinessDate::today()) {
            throw ValidationException::withMessages(['effective_from' => 'La vigencia debe comenzar hoy o en una fecha futura de Chile.']);
        }
        $price = DB::connection('core')->transaction(function () use ($service, $data) {
            PlatformService::query()->whereKey($service->id)->lockForUpdate()->firstOrFail();
            $overlap = PlatformServicePrice::query()->where('platform_service_id', $service->id)
                ->whereDate('effective_from', '<=', $data['effective_to'] ?? '9999-12-31')
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $data['effective_from']))->exists();
            if ($overlap) throw ValidationException::withMessages(['effective_from' => 'Cierra primero la vigencia anterior.']);
            return PlatformServicePrice::create(['platform_service_id' => $service->id] + $data);
        });
        return response()->json(['data' => $price], 201);
    }

    public function closePrice(Request $request, PlatformServicePrice $price)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $data = $request->validate(['effective_to' => ['required', 'date_format:Y-m-d']]);
        if ($data['effective_to'] < BusinessDate::today()) {
            throw ValidationException::withMessages(['effective_to' => 'La vigencia solo puede cerrarse desde hoy en Chile o una fecha futura.']);
        }
        abort_unless($price->effective_to === null && $price->effective_from->toDateString() <= $data['effective_to'], 422);
        $price->update($data);
        return response()->json(['data' => $price->refresh()]);
    }

    private function present(PlatformService $service): array
    {
        $prices = PlatformServicePrice::query()->where('platform_service_id', $service->id)->orderByDesc('effective_from')->get();
        $businessDate = BusinessDate::today();
        $current = $prices->first(fn ($item) => $item->effective_from->toDateString() <= $businessDate
            && ($item->effective_to === null || $item->effective_to->toDateString() >= $businessDate));
        return ['id' => $service->id, 'name' => $service->name, 'category' => $service->category,
            'duration_minutes' => $service->duration_minutes, 'is_active' => $service->is_active,
            'base_price_clp' => $current?->amount_clp, 'prices' => $prices];
    }

    private function ensureNameAvailable(string $name, string $category, ?int $except = null): void
    {
        $exists = PlatformService::query()->where('category', $category)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($except, fn ($query) => $query->whereKeyNot($except))->exists();
        if ($exists) throw ValidationException::withMessages(['name' => 'Ya existe una prestación de este tipo con ese nombre.']);
    }
}
