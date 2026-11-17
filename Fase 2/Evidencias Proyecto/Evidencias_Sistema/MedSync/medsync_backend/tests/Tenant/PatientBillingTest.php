<?php

use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
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
    $schema->create('patients', function (Blueprint $t) { $t->id(); $t->string('first_name'); $t->string('last_name'); $t->string('rut'); $t->timestamps(); $t->softDeletes(); });
    $schema->create('professionals', function (Blueprint $t) { $t->id(); $t->string('first_name'); $t->string('last_name'); $t->timestamps(); $t->softDeletes(); });
    $schema->create('services', function (Blueprint $t) { $t->id(); $t->string('name'); $t->boolean('is_active')->default(true); $t->timestamps(); });
    $schema->create('appointments', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('patient_id'); $t->unsignedBigInteger('professional_id'); $t->unsignedBigInteger('service_id'); $t->string('status'); $t->timestamps(); });
    $schema->create('service_prices', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('service_id'); $t->unsignedBigInteger('amount_clp'); $t->date('effective_from'); $t->date('effective_to')->nullable(); $t->timestamps(); });
    (require database_path('migrations/center/2026_11_14_000001_create_patient_billing_tables.php'))->up();
    $now = now();
    DB::connection('center')->table('patients')->insert(['id'=>1,'first_name'=>'Ana','last_name'=>'Soto','rut'=>'12345678-5','created_at'=>$now,'updated_at'=>$now]);
    DB::connection('center')->table('professionals')->insert([['id'=>1,'first_name'=>'Dra','last_name'=>'Uno','created_at'=>$now,'updated_at'=>$now], ['id'=>2,'first_name'=>'Dr','last_name'=>'Dos','created_at'=>$now,'updated_at'=>$now]]);
    DB::connection('center')->table('services')->insert([['id'=>1,'name'=>'Consulta','is_active'=>true,'created_at'=>$now,'updated_at'=>$now], ['id'=>2,'name'=>'Examen','is_active'=>true,'created_at'=>$now,'updated_at'=>$now]]);
    DB::connection('center')->table('appointments')->insert(['id'=>1,'patient_id'=>1,'professional_id'=>1,'service_id'=>1,'status'=>'ATENDIDA','created_at'=>$now,'updated_at'=>$now]);
    DB::connection('center')->table('service_prices')->insert(['service_id'=>2,'amount_clp'=>10000,'effective_from'=>today()->subDay(),'created_at'=>$now,'updated_at'=>$now]);
}

function billingActor(MedicalCenter $center, string $role, ?int $professionalId = null, ?int $patientId = null): User
{
    $user = User::create(['name'=>$role.uniqid(), 'email'=>uniqid().'@example.test', 'password'=>'Password123', 'is_active'=>true]);
    CenterUser::create(['medical_center_id'=>$center->id,'user_id'=>$user->id,'role'=>$role,'professional_id'=>$professionalId,'patient_id'=>$patientId,'is_active'=>true]);
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
    $payment = $this->postJson('/api/v1/billing/patients/1/payments', ['amount_clp'=>4000,'method'=>'CASH'])
        ->assertCreated()->json('data.id');
    $this->postJson('/api/v1/billing/patients/1/payments', ['amount_clp'=>7000,'method'=>'CASH'])->assertUnprocessable();
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
