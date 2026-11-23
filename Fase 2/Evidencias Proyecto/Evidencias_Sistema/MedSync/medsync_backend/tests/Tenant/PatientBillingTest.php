<?php

use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Models\Core\PlatformService;
use App\Models\Core\PlatformServicePrice;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function prepareBillingTenant(string $path): void
{
    config(['database.connections.center' => array_merge(config('database.connections.center'), [
        'driver' => 'sqlite', 'url' => null, 'database' => $path,
    ])]);
    DB::purge('center');
    $schema = Schema::connection('center');
    $schema->create('patients', function (Blueprint $t) { $t->id(); $t->string('first_name'); $t->string('last_name'); $t->string('rut'); $t->string('email')->nullable(); $t->unsignedBigInteger('user_id')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); $t->softDeletes(); });
    $schema->create('professionals', function (Blueprint $t) { $t->id(); $t->string('first_name'); $t->string('last_name'); $t->timestamps(); $t->softDeletes(); });
    $schema->create('services', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('specialty_id')->nullable(); $t->string('name'); $t->string('category')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); });
    $schema->create('appointments', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('patient_id'); $t->unsignedBigInteger('professional_id'); $t->unsignedBigInteger('service_id'); $t->string('status'); $t->timestamps(); });
    $schema->create('service_prices', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('service_id'); $t->unsignedBigInteger('amount_clp'); $t->date('effective_from'); $t->date('effective_to')->nullable(); $t->timestamps(); });
    (require database_path('migrations/center/2026_11_14_000001_create_patient_billing_tables.php'))->up();
    (require database_path('migrations/center/2026_11_20_000001_link_services_and_allocate_payments.php'))->up();
    (require database_path('migrations/center/2026_11_22_000002_add_shared_exam_to_services.php'))->up();
    (require database_path('migrations/center/2026_11_22_000003_add_payment_idempotency.php'))->up();
    $now = now();
    DB::connection('center')->table('patients')->insert(['id'=>1,'first_name'=>'Ana','last_name'=>'Soto','rut'=>'12345678-5','created_at'=>$now,'updated_at'=>$now]);
    DB::connection('center')->table('professionals')->insert([['id'=>1,'first_name'=>'Dra','last_name'=>'Uno','created_at'=>$now,'updated_at'=>$now], ['id'=>2,'first_name'=>'Dr','last_name'=>'Dos','created_at'=>$now,'updated_at'=>$now]]);
    DB::connection('center')->table('services')->insert([['id'=>1,'name'=>'Consulta','category'=>'CONSULTATION','is_active'=>true,'created_at'=>$now,'updated_at'=>$now], ['id'=>2,'name'=>'Examen','category'=>'EXAM','is_active'=>true,'created_at'=>$now,'updated_at'=>$now]]);
    DB::connection('center')->table('appointments')->insert(['id'=>1,'patient_id'=>1,'professional_id'=>1,'service_id'=>1,'status'=>'ATENDIDA','created_at'=>$now,'updated_at'=>$now]);
    DB::connection('center')->table('service_prices')->insert(['service_id'=>2,'amount_clp'=>10000,'effective_from'=>today()->subDay(),'created_at'=>$now,'updated_at'=>$now]);
}

function billingActor(MedicalCenter $center, string $role, ?int $professionalId = null, ?int $patientId = null): User
{
    $user = User::create(['name'=>$role.uniqid(), 'email'=>uniqid().'@example.test', 'password'=>'Password123', 'is_active'=>true]);
    CenterUser::create(['medical_center_id'=>$center->id,'user_id'=>$user->id,'role'=>$role,'professional_id'=>$professionalId,'patient_id'=>$patientId,'is_active'=>true]);
    if ($role === 'PACIENTE' && $patientId) DB::connection('center')->table('patients')->where('id', $patientId)->update(['user_id' => $user->id, 'email' => $user->email]);
    return $user;
}

beforeEach(function () {
    $this->tenantFile = tempnam(sys_get_temp_dir(), 'medsync_billing_');
    prepareBillingTenant($this->tenantFile);
    $this->center = MedicalCenter::create(['name'=>'Centro Cobros','slug'=>'centro-cobros','database_name'=>$this->tenantFile,'rut'=>'76543210-3','is_active'=>true]);
    $this->professional = billingActor($this->center, 'PROFESIONAL', 1);
    $this->otherProfessional = billingActor($this->center, 'PROFESIONAL', 2);
    $this->reception = billingActor($this->center, 'RECEPCIONISTA');
    $this->admin = billingActor($this->center, 'ADMIN');
    $this->patient = billingActor($this->center, 'PACIENTE', null, 1);
});

it('lets the assigned professional charge directly and tracks a partial then full payment per charge', function () {
    $session = ['active_medical_center_id' => $this->center->id];
    $this->actingAs($this->professional, 'sanctum')->withHeader('Origin', 'http://localhost:3000')->withSession($session);
    $id = $this->postJson('/api/v1/appointments/1/charges', ['service_id' => 2])
        ->assertCreated()->assertJsonPath('data.status', 'CHARGED')->json('data.id');
    $this->postJson('/api/v1/appointments/1/charges', ['service_id' => 2])->assertUnprocessable();
    $this->actingAs($this->patient, 'sanctum')->withSession($session)
        ->getJson('/api/v1/billing/mine')->assertOk()->assertJsonPath('data.charges.0.payment_status', 'PENDING');
    $this->actingAs($this->reception, 'sanctum')->withSession($session);
    $this->postJson('/api/v1/billing/patients/1/payments', ['amount_clp' => 4000, 'method' => 'CASH', 'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        'allocations' => [['care_indication_id' => $id, 'amount_clp' => 4000]]])->assertCreated();
    $this->actingAs($this->patient, 'sanctum')->withSession($session)
        ->getJson('/api/v1/billing/mine')->assertJsonPath('data.charges.0.payment_status', 'PARTIAL')
        ->assertJsonPath('data.charges.0.balance_clp', 6000);
    $this->actingAs($this->reception, 'sanctum')->withSession($session)
        ->postJson('/api/v1/billing/patients/1/payments', ['amount_clp' => 6000, 'method' => 'CARD', 'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'allocations' => [['care_indication_id' => $id, 'amount_clp' => 6000]]])->assertCreated();
    $this->actingAs($this->patient, 'sanctum')->withSession($session)
        ->getJson('/api/v1/billing/mine')->assertJsonPath('data.charges.0.payment_status', 'PAID')
        ->assertJsonPath('data.balance_clp', 0);
});

it('replays one payment safely and accepts a distinct legitimate payment', function () {
    $session = ['active_medical_center_id' => $this->center->id];
    $this->actingAs($this->professional, 'sanctum')->withHeader('Origin', 'http://localhost:3000')->withSession($session)
        ->postJson('/api/v1/appointments/1/charges', ['service_id' => 2])->assertCreated();
    $this->actingAs($this->reception, 'sanctum')->withSession($session);
    $key = (string) \Illuminate\Support\Str::uuid();
    $payload = ['amount_clp' => 4000, 'method' => 'CASH', 'idempotency_key' => $key];
    $id = $this->postJson('/api/v1/billing/patients/1/payments', $payload)->assertCreated()->json('data.id');
    $this->postJson('/api/v1/billing/patients/1/payments', $payload)->assertOk()->assertJsonPath('data.id', $id);
    $this->postJson('/api/v1/billing/patients/1/payments', [...$payload, 'amount_clp' => 3000])
        ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
    $this->postJson('/api/v1/billing/patients/1/payments', [
        'amount_clp' => 6000, 'method' => 'CASH', 'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
    ])->assertCreated();
    expect(DB::connection('center')->table('patient_payments')->count())->toBe(2);
    $this->getJson('/api/v1/billing/summary')->assertJsonPath('data.balance_clp', 0);
});

it('requires the appointment specialty unless an exam is explicitly shared', function () {
    DB::connection('center')->table('services')->where('id', 1)->update(['specialty_id' => 1]);
    DB::connection('center')->table('services')->where('id', 2)->update(['specialty_id' => 2]);
    $session = ['active_medical_center_id' => $this->center->id];
    $this->actingAs($this->professional, 'sanctum')->withHeader('Origin', 'http://localhost:3000')->withSession($session);
    $this->postJson('/api/v1/appointments/1/charges', ['service_id' => 2])
        ->assertUnprocessable()->assertJsonValidationErrors('service_id');
    DB::connection('center')->table('services')->where('id', 2)->update(['is_shared_exam' => true]);
    $this->postJson('/api/v1/appointments/1/charges', ['service_id' => 2])
        ->assertCreated()->assertJsonPath('data.amount_clp', 10000);
});

it('lets the same professional convert a pending indication into one charge', function () {
    $session = ['active_medical_center_id' => $this->center->id];
    $this->actingAs($this->professional, 'sanctum')->withHeader('Origin', 'http://localhost:3000')->withSession($session);
    $id = $this->postJson('/api/v1/appointments/1/indications', ['service_id' => 2])
        ->assertCreated()->assertJsonPath('data.status', 'INDICATED')->json('data.id');
    $this->postJson('/api/v1/appointments/1/charges', ['service_id' => 2])
        ->assertCreated()->assertJsonPath('data.id', $id)->assertJsonPath('data.status', 'CHARGED');
    $this->postJson('/api/v1/appointments/1/charges', ['service_id' => 2])->assertUnprocessable();
    expect(DB::connection('center')->table('care_indications')->count())->toBe(1);
});

it('uses the Core base price when the center has no override and preserves it in the charge', function () {
    $catalog = PlatformService::create(['name' => 'Examen global', 'category' => 'EXAM', 'duration_minutes' => 30]);
    PlatformServicePrice::create(['platform_service_id' => $catalog->id, 'amount_clp' => 18000,
        'effective_from' => today()->subDay()->toDateString()]);
    DB::connection('center')->table('service_prices')->where('service_id', 2)->delete();
    $session = ['active_medical_center_id' => $this->center->id];
    $this->actingAs($this->admin, 'sanctum')->withHeader('Origin', 'http://localhost:3000')->withSession($session)
        ->patchJson('/api/v1/services/2/platform-link', ['platform_service_id' => $catalog->id])->assertOk();
    $this->actingAs($this->admin, 'sanctum')->withSession($session)
        ->getJson('/api/v1/center/platform-services')->assertOk()
        ->assertJsonPath('data.0.base_price_clp', 18000)
        ->assertJsonPath('data.0.effective_price_clp', 18000);
    $this->actingAs($this->professional, 'sanctum')->withSession($session);
    $id = $this->postJson('/api/v1/appointments/1/charges', ['service_id' => 2])
        ->assertCreated()->assertJsonPath('data.amount_clp', 18000)
        ->assertJsonPath('data.price_source', 'CORE')->json('data.id');
    PlatformServicePrice::query()->update(['amount_clp' => 22000]);
    $this->actingAs($this->patient, 'sanctum')->withSession($session)
        ->getJson('/api/v1/billing/mine')->assertJsonPath('data.charges.0.amount_clp', 18000)
        ->assertJsonPath('data.charges.0.payment_status', 'PENDING');
});

it('requires matching local and global categories when linking services', function () {
    $catalog = PlatformService::create(['name' => 'Consulta global', 'category' => 'CONSULTATION', 'duration_minutes' => 30]);
    $session = ['active_medical_center_id' => $this->center->id];

    $this->actingAs($this->admin, 'sanctum')->withHeader('Origin', 'http://localhost:3000')->withSession($session)
        ->patchJson('/api/v1/services/2/platform-link', ['platform_service_id' => $catalog->id])
        ->assertUnprocessable()
        ->assertJsonPath('errors.platform_service_id.0', 'Las categorías no coinciden. Solo puedes vincular prestaciones de la misma categoría.');

    $this->patchJson('/api/v1/services/1/platform-link', ['platform_service_id' => $catalog->id])->assertOk()
        ->assertJsonPath('data.platform_service_id', $catalog->id)
        ->assertJsonPath('data.category', 'CONSULTATION');
});

it('lets an administrator classify a legacy service and prevents local category drift after linking', function () {
    DB::connection('center')->table('services')->where('id', 1)->update(['category' => null]);
    $global = PlatformService::create(['name' => 'Consulta global', 'category' => 'CONSULTATION', 'duration_minutes' => 30]);
    $session = ['active_medical_center_id' => $this->center->id];
    $this->actingAs($this->admin, 'sanctum')->withHeader('Origin', 'http://localhost:3000')->withSession($session);
    $this->patchJson('/api/v1/services/1/platform-link', ['platform_service_id' => $global->id])
        ->assertUnprocessable();
    $this->patchJson('/api/v1/services/1', ['category' => 'CONSULTATION'])->assertOk()
        ->assertJsonPath('data.category', 'CONSULTATION');
    $this->patchJson('/api/v1/services/1/platform-link', ['platform_service_id' => $global->id])->assertOk();
    $this->patchJson('/api/v1/services/1', ['category' => 'EXAM'])
        ->assertUnprocessable()->assertJsonValidationErrors('category');
    expect(DB::connection('center')->table('services')->where('id', 1)->value('category'))->toBe('CONSULTATION');
});

it('prevents a global category change while a center service is linked', function () {
    $catalog = PlatformService::create(['name' => 'Examen global con tarifa', 'category' => 'EXAM', 'duration_minutes' => 30]);
    PlatformServicePrice::create(['platform_service_id' => $catalog->id, 'amount_clp' => 18000,
        'effective_from' => today()->subDay()->toDateString()]);
    $session = ['active_medical_center_id' => $this->center->id];
    $this->actingAs($this->admin, 'sanctum')->withHeader('Origin', 'http://localhost:3000')->withSession($session)
        ->patchJson('/api/v1/services/2/platform-link', ['platform_service_id' => $catalog->id])->assertOk();

    $superAdmin = User::create(['name' => 'Superadmin', 'email' => uniqid().'@example.test', 'password' => 'Password123', 'system_role' => 'SUPER_ADMIN', 'is_active' => true]);
    $this->actingAs($superAdmin, 'sanctum')->patchJson("/api/v1/platform/services/{$catalog->id}", ['category' => 'DIAGNOSIS'])->assertUnprocessable();
    $response = $this->actingAs($this->admin, 'sanctum')->withSession($session)->getJson('/api/v1/center/platform-services')->assertOk();
    $linked = collect($response->json('data'))->firstWhere('id', $catalog->id);
    expect($linked['effective_price_clp'])->toBe(10000);
    expect($catalog->refresh()->category)->toBe('EXAM');
});

afterEach(function () {
    DB::purge('center');
    if (is_file($this->tenantFile)) unlink($this->tenantFile);
});

it('separates an indication from confirmed debt and records partial payment', function () {
    $session = ['active_medical_center_id' => $this->center->id];
    $this->actingAs($this->professional, 'sanctum')->withHeader('Origin', 'http://localhost:3000')->withSession($session);
    $id = $this->postJson('/api/v1/appointments/1/indications', ['service_id'=>2])
        ->assertCreated()->assertJsonPath('data.amount_clp', 10000)->json('data.id');
    $this->postJson('/api/v1/appointments/1/indications', ['service_id'=>2])->assertUnprocessable();
    $this->actingAs($this->otherProfessional, 'sanctum')->withSession($session)
        ->postJson('/api/v1/appointments/1/indications', ['service_id'=>2])->assertForbidden();
    $this->actingAs($this->patient, 'sanctum')->withSession($session)
        ->postJson("/api/v1/billing/indications/{$id}/confirm")->assertForbidden();
    $this->actingAs($this->reception, 'sanctum')->withSession($session)
        ->getJson('/api/v1/billing/summary')->assertOk()->assertJsonPath('data.balance_clp', 0)
        ->assertJsonPath('data.pending_indications', 1);
    $this->postJson("/api/v1/billing/indications/{$id}/confirm")->assertOk();
    $this->postJson("/api/v1/billing/indications/{$id}/confirm")->assertUnprocessable();
    $payment = $this->postJson('/api/v1/billing/patients/1/payments', ['amount_clp'=>4000,'method'=>'CASH','idempotency_key'=>(string) \Illuminate\Support\Str::uuid()])
        ->assertCreated()->json('data.id');
    $this->postJson('/api/v1/billing/patients/1/payments', ['amount_clp'=>7000,'method'=>'CASH','idempotency_key'=>(string) \Illuminate\Support\Str::uuid()])->assertUnprocessable();
    $this->getJson('/api/v1/billing/summary')->assertOk()
        ->assertJsonPath('data.charged_clp', 10000)->assertJsonPath('data.paid_clp', 4000)
        ->assertJsonPath('data.balance_clp', 6000)->assertJsonPath('data.patients.0.balance_clp', 6000);
    $this->postJson("/api/v1/billing/indications/{$id}/void", ['reason'=>'Error de prestación'])->assertUnprocessable();
    $this->postJson("/api/v1/billing/payments/{$payment}/void", ['reason'=>'Error de caja'])->assertForbidden();
    $this->actingAs($this->admin, 'sanctum')->withSession($session)
        ->postJson("/api/v1/billing/payments/{$payment}/void", ['reason'=>'Error de caja'])->assertOk();
    $this->postJson("/api/v1/billing/indications/{$id}/void", ['reason'=>'Error de prestación'])->assertOk();
    $this->getJson('/api/v1/billing/summary')->assertOk()->assertJsonPath('data.balance_clp', 0);
});

it('keeps billing records inside the active center database', function () {
    $otherFile = tempnam(sys_get_temp_dir(), 'medsync_billing_other_');
    try {
        $this->actingAs($this->professional, 'sanctum')->withHeader('Origin', 'http://localhost:3000')
            ->withSession(['active_medical_center_id'=>$this->center->id]);
        $indication = $this->postJson('/api/v1/appointments/1/indications', ['service_id'=>2])
            ->assertCreated()->json('data.id');
        $this->actingAs($this->reception, 'sanctum')->withSession(['active_medical_center_id'=>$this->center->id])
            ->postJson("/api/v1/billing/indications/{$indication}/confirm")->assertOk();
        $this->getJson('/api/v1/billing/summary')->assertJsonPath('data.balance_clp', 10000);

        prepareBillingTenant($otherFile);
        $otherCenter = MedicalCenter::create(['name'=>'Otro Centro','slug'=>'otro-centro','database_name'=>$otherFile,'rut'=>'76543211-1','is_active'=>true]);
        $otherAdmin = billingActor($otherCenter, 'ADMIN');
        $this->actingAs($otherAdmin, 'sanctum')->withHeader('Origin', 'http://localhost:3000')->withSession(['active_medical_center_id'=>$otherCenter->id])
            ->getJson('/api/v1/billing/summary')->assertOk()->assertJsonPath('data.balance_clp', 0);
        $this->getJson('/api/v1/appointments/1/indications')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/billing/indications/{$indication}/confirm")->assertNotFound();
    } finally {
        DB::purge('center');
        if (is_file($otherFile)) unlink($otherFile);
    }
});
