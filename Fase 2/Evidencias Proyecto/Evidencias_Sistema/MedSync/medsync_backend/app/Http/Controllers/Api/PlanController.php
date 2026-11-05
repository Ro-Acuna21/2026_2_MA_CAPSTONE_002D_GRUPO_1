<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Core\Plan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function publicIndex()
    {
        return response()->json(['data' => Plan::query()->where('is_active', true)->orderBy('monthly_price_clp')->get()]);
    }

    public function index(Request $request)
    {
        $this->authorizePlatform($request);
        return response()->json(['data' => Plan::query()->orderBy('monthly_price_clp')->get()]);
    }

    public function store(Request $request)
    {
        $this->authorizePlatform($request);
        $data = $this->validated($request, true);
        $plan = Plan::create($data);
        return response()->json(['data' => $plan], 201);
    }

    public function update(Request $request, Plan $plan)
    {
        $this->authorizePlatform($request);
        $plan->update($this->validated($request, false, $plan));
        return response()->json(['data' => $plan->refresh()]);
    }

    private function validated(Request $request, bool $create, ?Plan $plan = null): array
    {
        $required = $create ? 'required' : 'sometimes';
        return $request->validate([
            'name' => [$required, 'string', 'min:2', 'max:120'],
            'code' => [$required, 'string', 'regex:/^[A-Z0-9_]+$/', 'max:40', Rule::unique('core.plans', 'code')->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'monthly_price_clp' => [$required, 'integer', 'min:0', 'max:999999999'],
            'professional_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'user_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'features' => ['nullable', 'array', 'max:20'],
            'features.*' => ['string', 'max:200'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function authorizePlatform(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
    }
}
