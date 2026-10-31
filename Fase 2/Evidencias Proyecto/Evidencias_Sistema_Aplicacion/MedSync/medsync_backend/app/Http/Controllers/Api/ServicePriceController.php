<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Center\Service;
use App\Models\Center\ServicePrice;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicePriceController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $query = ServicePrice::query()->orderBy('service_id')->orderByDesc('effective_from');
        if ($request->filled('service_id')) {
            $data = $request->validate(['service_id' => ['integer', 'min:1']]);
            $query->where('service_id', $data['service_id']);
        }
        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'service_id' => ['required', 'integer'], 'amount_clp' => ['required', 'integer', 'min:0', 'max:999999999'],
            'effective_from' => ['required', 'date_format:Y-m-d'], 'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
        ]);
        Service::findOrFail($data['service_id']);
        $price = DB::connection('center')->transaction(function () use ($data) {
            // Bloquea la prestación para serializar cambios de precio concurrentes.
            Service::query()->whereKey($data['service_id'])->lockForUpdate()->firstOrFail();
            $this->ensureNoOverlap($data['service_id'], $data['effective_from'], $data['effective_to'] ?? null);
            return ServicePrice::create($data);
        });
        return response()->json(['data' => $price], 201);
    }

    public function update(Request $request, ServicePrice $price)
    {
        $this->authorizeAdmin($request);
        $future = $price->effective_from->isFuture();
        abort_unless($future || ($price->effective_to === null && $price->effective_from->isPast()), 422, 'El historial cerrado no se modifica.');
        $data = $request->validate([
            'amount_clp' => $future ? ['sometimes', 'integer', 'min:0', 'max:999999999'] : ['prohibited'],
            'effective_from' => $future ? ['sometimes', 'date_format:Y-m-d', 'after:today'] : ['prohibited'],
            'effective_to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        if (!$future && (!isset($data['effective_to']) || $data['effective_to'] < now()->toDateString())) {
            throw ValidationException::withMessages(['effective_to' => 'Cierra el precio vigente desde hoy o una fecha futura.']);
        }
        $from = $data['effective_from'] ?? $price->effective_from->toDateString();
        $to = array_key_exists('effective_to', $data) ? $data['effective_to'] : $price->effective_to?->toDateString();
        if ($to !== null && $to < $from) throw ValidationException::withMessages(['effective_to' => 'La fecha final debe ser posterior al inicio.']);
        DB::connection('center')->transaction(function () use ($price, $data, $from, $to) {
            Service::query()->whereKey($price->service_id)->lockForUpdate()->firstOrFail();
            $this->ensureNoOverlap($price->service_id, $from, $to, $price->id);
            $price->update($data);
        });
        return response()->json(['data' => $price->refresh()]);
    }

    public function destroy(Request $request, ServicePrice $price)
    {
        $this->authorizeAdmin($request);
        $this->ensureFuture($price);
        $price->delete();
        return response()->noContent();
    }

    private function ensureFuture(ServicePrice $price): void
    {
        abort_unless($price->effective_from->isFuture(), 422, 'El historial vigente o pasado no se modifica.');
    }

    private function ensureNoOverlap(int $serviceId, string $from, ?string $to, ?int $except = null): void
    {
        $query = ServicePrice::query()->where('service_id', $serviceId)
            ->where(function ($q) use ($from) { $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from); });
        if ($to !== null) $query->whereDate('effective_from', '<=', $to);
        if ($except !== null) $query->whereKeyNot($except);
        if ($query->exists()) throw ValidationException::withMessages(['effective_from' => 'El rango se superpone con un precio existente. Cierra primero la vigencia anterior.']);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($this->tenant->centerUser()->role === 'ADMIN' && $request->user()->can('service_prices.manage'), 403);
    }
}
