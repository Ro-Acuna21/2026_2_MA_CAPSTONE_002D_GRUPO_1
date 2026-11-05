<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Core\MedicalCenter;
use App\Models\Core\Plan;
use App\Models\Core\Subscription;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function platformShow(Request $request, MedicalCenter $center)
    {
        $this->authorizePlatform($request);
        return response()->json(['data' => $this->presentCenter($center)]);
    }

    public function centerShow(Request $request)
    {
        abort_unless($this->tenant->centerUser()->role === 'ADMIN', 403);
        return response()->json(['data' => $this->presentCenter($this->tenant->medicalCenter())]);
    }

    public function store(Request $request, MedicalCenter $center)
    {
        $this->authorizePlatform($request);
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:core.plans,id'],
            'status' => ['required', Rule::in(['TRIAL', 'ACTIVE', 'EXPIRED', 'SUSPENDED'])],
            'started_at' => ['required', 'date_format:Y-m-d'],
            'renews_at' => ['nullable', 'date_format:Y-m-d', 'after:started_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $plan = Plan::findOrFail($data['plan_id']);
        if (!$plan->is_active) throw ValidationException::withMessages(['plan_id' => 'El plan está inactivo.']);
        DB::connection('core')->transaction(function () use ($center, $plan, $data) {
            MedicalCenter::query()->whereKey($center->id)->lockForUpdate()->firstOrFail();
            $previous = Subscription::query()->where('medical_center_id', $center->id)->whereNull('ended_at')->lockForUpdate()->first();
            if ($previous) $previous->update(['ended_at' => now()]);
            Subscription::create([
                'medical_center_id' => $center->id, 'plan_id' => $plan->id, 'status' => $data['status'],
                'started_at' => $data['started_at'].' 00:00:00', 'renews_at' => isset($data['renews_at']) ? $data['renews_at'].' 00:00:00' : null,
                'notes' => $data['notes'] ?? null, 'monthly_price_clp_snapshot' => $plan->monthly_price_clp,
            ]);
            $center->update(['plan' => in_array($plan->code, ['STARTER', 'PRO'], true) ? $plan->code : null, 'subscription_status' => $data['status'], 'subscription_ends_at' => $data['renews_at'] ?? null]);
        });
        return response()->json(['data' => $this->presentCenter($center->fresh())], 201);
    }

    public function update(Request $request, MedicalCenter $center, Subscription $subscription)
    {
        $this->authorizePlatform($request);
        abort_unless($subscription->medical_center_id === $center->id && $subscription->ended_at === null, 404);
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['TRIAL', 'ACTIVE', 'EXPIRED', 'SUSPENDED'])],
            'renews_at' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if (isset($data['renews_at']) && $data['renews_at'] <= $subscription->started_at->toDateString()) {
            throw ValidationException::withMessages(['renews_at' => 'La fecha de renovación debe ser posterior al inicio.']);
        }
        DB::connection('core')->transaction(function () use ($center, $subscription, $data) {
            MedicalCenter::query()->whereKey($center->id)->lockForUpdate()->firstOrFail();
            $subscription->refresh();
            abort_unless($subscription->ended_at === null, 409);
            $subscription->update([
                ...$data,
                'renews_at' => array_key_exists('renews_at', $data) ? ($data['renews_at'] ? $data['renews_at'].' 00:00:00' : null) : $subscription->renews_at,
            ]);
            $center->update(['subscription_status' => $subscription->status, 'subscription_ends_at' => $subscription->renews_at]);
        });
        return response()->json(['data' => $this->presentCenter($center->fresh())]);
    }

    public function payment(Request $request, MedicalCenter $center, Subscription $subscription)
    {
        $this->authorizePlatform($request);
        abort_unless($subscription->medical_center_id === $center->id, 404);
        $data = $request->validate([
            'amount_clp' => ['required', 'integer', 'min:1', 'max:999999999'],
            'status' => ['required', Rule::in(['PENDING', 'PAID', 'OVERDUE'])],
            'due_at' => ['required', 'date_format:Y-m-d'],
            'paid_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'provider_reference' => ['nullable', 'string', 'max:255'],
        ]);
        if ($data['status'] === 'PAID' && (empty($data['paid_at']) || empty(trim($data['provider_reference'] ?? '')))) {
            throw ValidationException::withMessages(['paid_at' => 'Un pago registrado requiere fecha y referencia del comprobante.']);
        }
        if ($data['status'] !== 'PAID' && !empty($data['paid_at'])) {
            throw ValidationException::withMessages(['paid_at' => 'Solo un pago registrado puede tener fecha de pago.']);
        }
        if ($data['status'] === 'PAID' && $subscription->payments()->where('provider_reference', trim($data['provider_reference']))->exists()) {
            throw ValidationException::withMessages(['provider_reference' => 'Ese comprobante ya fue registrado en esta suscripción.']);
        }
        $payment = $subscription->payments()->create([
            ...$data,
            'due_at' => $data['due_at'].' 00:00:00',
            'paid_at' => isset($data['paid_at']) ? $data['paid_at'].' 00:00:00' : null,
            'provider_reference' => trim($data['provider_reference'] ?? '') ?: null,
            'recorded_by' => $request->user()->id,
        ]);
        return response()->json(['data' => $payment], 201);
    }

    public function presentCenter(MedicalCenter $center): array
    {
        $center->load(['currentSubscription.plan', 'currentSubscription.payments', 'subscriptions.plan']);
        $current = $center->currentSubscription;
        return [
            'center_id' => $center->id,
            'current' => $current ? $this->presentSubscription($current, true) : null,
            'history' => $center->subscriptions->filter(fn ($item) => $item->ended_at !== null)->map(fn ($item) => $this->presentSubscription($item, false))->values(),
            'payment_mode' => 'MANUAL',
        ];
    }

    private function presentSubscription(Subscription $subscription, bool $withPayments): array
    {
        return [
            'id' => $subscription->id, 'status' => $subscription->status,
            'started_at' => $subscription->started_at?->toDateString(),
            'renews_at' => $subscription->renews_at?->toDateString(),
            'ended_at' => $subscription->ended_at?->toDateString(),
            'notes' => $subscription->notes,
            'monthly_price_clp_snapshot' => $subscription->monthly_price_clp_snapshot,
            'plan' => $subscription->plan?->only(['id', 'code', 'name', 'monthly_price_clp', 'professional_limit', 'user_limit']),
            'payments' => $withPayments ? $subscription->payments->map(fn ($payment) => [
                'id' => $payment->id, 'amount_clp' => $payment->amount_clp, 'status' => $payment->status,
                'due_at' => $payment->due_at?->toDateString(), 'paid_at' => $payment->paid_at?->toDateString(),
                'provider_reference' => $payment->provider_reference, 'recorded_at' => $payment->created_at?->toIso8601String(),
            ]) : [],
        ];
    }

    private function authorizePlatform(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
    }
}
