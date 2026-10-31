<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function __invoke(Request $request)
    {
        abort_unless($this->tenant->centerUser()->role === 'ADMIN' && $request->user()->can('reports.view'), 403);
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'service_id' => ['nullable', 'integer', 'min:1'], 'professional_id' => ['nullable', 'integer', 'min:1'],
        ]);
        if (CarbonImmutable::parse($data['from'])->diffInDays(CarbonImmutable::parse($data['to'])) > 366) {
            throw ValidationException::withMessages(['to' => 'El período máximo es de 366 días.']);
        }
        $query = DB::connection('center')->table('appointments as a')
            ->join('services as s', 's.id', '=', 'a.service_id')
            ->join('professionals as p', 'p.id', '=', 'a.professional_id')
            ->whereBetween('a.appointment_date', [$data['from'], $data['to']])
            ->select('a.appointment_date', 'a.status', 'a.service_id', 'a.professional_id', 's.name as service_name', 'p.first_name', 'p.last_name');
        if (!empty($data['service_id'])) $query->where('a.service_id', $data['service_id']);
        if (!empty($data['professional_id'])) $query->where('a.professional_id', $data['professional_id']);
        $appointments = $query->get();
        $prices = DB::connection('center')->table('service_prices')
            ->whereIn('service_id', $appointments->pluck('service_id')->unique()->all())
            ->whereDate('effective_from', '<=', $data['to'])
            ->where(function ($q) use ($data) { $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $data['from']); })
            ->orderByDesc('effective_from')->get()->groupBy('service_id');
        $counts = ['PENDIENTE' => 0, 'CONFIRMADA' => 0, 'ATENDIDA' => 0, 'CANCELADA' => 0, 'NO_SHOW' => 0];
        $months = []; $services = []; $professionals = [];
        $estimated = 0; $lost = 0; $unpriced = 0;
        foreach ($appointments as $row) {
            $counts[$row->status] = ($counts[$row->status] ?? 0) + 1;
            $day = substr($row->appointment_date, 0, 10);
            $price = ($prices[$row->service_id] ?? collect())->first(fn ($item) => substr($item->effective_from, 0, 10) <= $day && ($item->effective_to === null || substr($item->effective_to, 0, 10) >= $day));
            $amount = $price ? (int) $price->amount_clp : 0;
            if (!$price && in_array($row->status, ['ATENDIDA', 'CANCELADA', 'NO_SHOW'], true)) $unpriced++;
            $revenue = $row->status === 'ATENDIDA' ? $amount : 0;
            $loss = in_array($row->status, ['CANCELADA', 'NO_SHOW'], true) ? $amount : 0;
            $estimated += $revenue; $lost += $loss;
            $month = substr($day, 0, 7);
            $months[$month] ??= ['month' => $month, 'total' => 0, 'bookings' => 0];
            $months[$month]['total'] += $revenue; $months[$month]['bookings']++;
            $services[$row->service_id] ??= ['id' => $row->service_id, 'label' => $row->service_name, 'bookings' => 0, 'attended' => 0, 'revenue' => 0];
            $professionals[$row->professional_id] ??= ['id' => $row->professional_id, 'label' => trim($row->first_name.' '.$row->last_name), 'bookings' => 0, 'attended' => 0, 'revenue' => 0];
            foreach ([&$services[$row->service_id], &$professionals[$row->professional_id]] as &$group) {
                $group['bookings']++; $group['revenue'] += $revenue;
                if ($row->status === 'ATENDIDA') $group['attended']++;
            }
            unset($group);
        }
        ksort($months);
        return response()->json(['data' => [
            'from' => $data['from'], 'to' => $data['to'], 'total_appointments' => $appointments->count(),
            'counts' => $counts, 'estimated_revenue_clp' => $estimated, 'lost_estimate_clp' => $lost,
            'unpriced_appointments' => $unpriced, 'average_ticket_clp' => $counts['ATENDIDA'] ? intdiv($estimated, $counts['ATENDIDA']) : 0,
            'months' => array_values($months), 'services' => array_values($services), 'professionals' => array_values($professionals),
        ]]);
    }
}
