<?php

namespace App\Services\Billing;

use App\Models\Center\Service;
use App\Models\Center\ServicePrice;
use App\Models\Core\PlatformServicePrice;

class EffectiveServicePrice
{
    public function forDate(Service $service, string $date): ?array
    {
        $override = ServicePrice::query()->where('service_id', $service->id)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('effective_from')->first();
        if ($override) return ['amount_clp' => (int) $override->amount_clp, 'source' => 'CENTER', 'record_id' => $override->id];
        if (!$service->platform_service_id) return null;
        $base = PlatformServicePrice::query()->where('platform_service_id', $service->platform_service_id)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('effective_from')->first();
        return $base ? ['amount_clp' => (int) $base->amount_clp, 'source' => 'CORE', 'record_id' => $base->id] : null;
    }
}
