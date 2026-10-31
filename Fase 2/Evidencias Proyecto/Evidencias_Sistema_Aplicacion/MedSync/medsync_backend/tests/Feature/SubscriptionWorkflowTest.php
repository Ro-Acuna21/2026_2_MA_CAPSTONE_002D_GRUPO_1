<?php

use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Models\Core\Subscription;
use App\Models\User;

beforeEach(function () {
    $this->platform = User::create(['name'=>'Plataforma', 'email'=>'platform-sub@example.test', 'password'=>'Password123', 'system_role'=>'SUPER_ADMIN', 'is_active'=>true]);
    $this->admin = User::create(['name'=>'Centro', 'email'=>'center-sub@example.test', 'password'=>'Password123', 'is_active'=>true]);
    $this->center = MedicalCenter::create(['name'=>'Centro Suscripción', 'slug'=>'centro-suscripcion', 'database_name'=>config('database.connections.center.database'), 'rut'=>'76123456-7', 'is_active'=>true, 'provisioning_status'=>'READY']);
    CenterUser::create(['medical_center_id'=>$this->center->id, 'user_id'=>$this->admin->id, 'role'=>'ADMIN', 'is_active'=>true]);
});
it('shares a persisted subscription between platform and center without inventing payment status', function () {
    $this->actingAs($this->platform, 'sanctum')->withHeader('Origin', 'http://localhost:3000');
    $plan = $this->postJson('/api/v1/platform/plans', [
        'name'=>'Profesional', 'code'=>'PRO', 'monthly_price_clp'=>89990, 'professional_limit'=>12,
        'user_limit'=>30, 'features'=>['Agenda y reservas'], 'is_active'=>true,
    ])->assertCreated()->json('data.id');
    $this->getJson('/api/public/plans')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/platform/centers')->assertJsonPath('data.0.subscription_status', null);
    $subscription = $this->postJson('/api/v1/platform/centers/'.$this->center->id.'/subscriptions', [
        'plan_id'=>$plan, 'status'=>'TRIAL', 'started_at'=>'2026-10-01', 'renews_at'=>'2026-11-01',
    ])->assertCreated()->assertJsonPath('data.current.status', 'TRIAL')->assertJsonCount(0, 'data.current.payments')->json('data.current.id');
    $this->postJson('/api/v1/platform/centers/'.$this->center->id.'/subscriptions/'.$subscription.'/payments', [
        'amount_clp'=>89990, 'status'=>'PAID', 'due_at'=>'2026-11-01',
    ])->assertUnprocessable();
    $this->postJson('/api/v1/platform/centers/'.$this->center->id.'/subscriptions/'.$subscription.'/payments', [
        'amount_clp'=>89990, 'status'=>'PAID', 'due_at'=>'2026-11-01', 'paid_at'=>'2026-10-31', 'provider_reference'=>'FICTICIO-1',
    ])->assertCreated()->assertJsonPath('data.recorded_by', $this->platform->id);
    $this->postJson('/api/v1/platform/centers/'.$this->center->id.'/subscriptions/'.$subscription.'/payments', [
        'amount_clp'=>89990, 'status'=>'PAID', 'due_at'=>'2026-11-01', 'paid_at'=>'2026-10-31', 'provider_reference'=>'FICTICIO-1',
    ])->assertUnprocessable();
    $this->patchJson('/api/v1/platform/centers/'.$this->center->id.'/subscriptions/'.$subscription, ['status'=>'ACTIVE'])->assertOk();
    $platformView = $this->getJson('/api/v1/platform/centers/'.$this->center->id.'/subscription')->assertOk()
        ->assertJsonPath('data.current.status', 'ACTIVE')->assertJsonPath('data.payment_mode', 'MANUAL')->json('data');
    $this->actingAs($this->admin, 'sanctum')->withSession(['active_medical_center_id'=>$this->center->id]);
    $centerView = $this->getJson('/api/v1/center/subscription')->assertOk()->json('data');
    expect($centerView)->toEqual($platformView);
    $this->postJson('/api/v1/platform/plans', ['name'=>'Intruso'])->assertForbidden();
    $this->postJson('/api/v1/platform/centers/'.$this->center->id.'/subscriptions', ['plan_id'=>$plan])->assertForbidden();
});
it('keeps price snapshots and one open subscription when plans change', function () {
    $this->actingAs($this->platform, 'sanctum')->withHeader('Origin', 'http://localhost:3000');
    $plan = $this->postJson('/api/v1/platform/plans', ['name'=>'Inicial','code'=>'STARTER','monthly_price_clp'=>49990])->assertCreated()->json('data.id');
    $first = $this->postJson('/api/v1/platform/centers/'.$this->center->id.'/subscriptions', ['plan_id'=>$plan,'status'=>'TRIAL','started_at'=>'2026-10-01'])->assertCreated()->json('data.current.id');
    $this->patchJson('/api/v1/platform/plans/'.$plan, ['monthly_price_clp'=>59990])->assertOk();
    $this->getJson('/api/v1/platform/centers/'.$this->center->id.'/subscription')->assertJsonPath('data.current.monthly_price_clp_snapshot', 49990);
    $this->postJson('/api/v1/platform/centers/'.$this->center->id.'/subscriptions', ['plan_id'=>$plan,'status'=>'ACTIVE','started_at'=>'2026-11-01'])->assertCreated()->assertJsonPath('data.current.monthly_price_clp_snapshot', 59990)->assertJsonCount(1, 'data.history');
    expect(Subscription::where('medical_center_id', $this->center->id)->whereNull('ended_at')->count())->toBe(1);
    $this->patchJson('/api/v1/platform/centers/'.$this->center->id.'/subscriptions/'.$first, ['status'=>'ACTIVE'])->assertNotFound();
});
