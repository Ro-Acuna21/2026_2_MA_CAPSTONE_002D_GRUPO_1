<?php

use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Models\Core\MedicalCenterSpecialty;
use App\Models\Core\Specialty;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

function prepareResultsTenant(string $path): void
{
    config(['database.connections.center' => array_merge(config('database.connections.center'), ['driver' => 'sqlite', 'url' => null, 'database' => $path])]);
    DB::purge('center');
    $schema = Schema::connection('center');
    $schema->create('patients', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('email')->nullable(); $t->boolean('is_active')->default(true); $t->string('first_name'); $t->string('last_name'); $t->timestamps(); $t->softDeletes(); });
    $schema->create('professionals', function (Blueprint $t) { $t->id(); $t->string('first_name'); $t->string('last_name'); $t->timestamps(); $t->softDeletes(); });
    $schema->create('services', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('specialty_id')->nullable(); $t->string('name'); $t->boolean('is_active')->default(true); $t->timestamps(); });
    $schema->create('appointments', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('patient_id'); $t->unsignedBigInteger('professional_id'); $t->unsignedBigInteger('service_id'); $t->date('appointment_date'); $t->string('status'); $t->timestamps(); });
    $schema->create('result_types', function (Blueprint $t) { $t->id(); $t->string('name'); $t->text('description')->nullable(); $t->boolean('is_active'); $t->timestamps(); });
    $schema->create('medical_results', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('patient_id'); $t->unsignedBigInteger('professional_id'); $t->unsignedBigInteger('result_type_id'); $t->unsignedBigInteger('appointment_id'); $t->timestamp('performed_at'); $t->string('status'); $t->timestamp('published_at')->nullable(); $t->unsignedBigInteger('created_by'); $t->unsignedBigInteger('published_by')->nullable(); $t->timestamp('voided_at')->nullable(); $t->unsignedBigInteger('voided_by')->nullable(); $t->text('void_reason')->nullable(); $t->timestamps(); $t->softDeletes(); });
    $schema->create('medical_result_files', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('medical_result_id'); $t->string('disk'); $t->string('storage_path'); $t->string('original_filename'); $t->string('mime_type'); $t->unsignedBigInteger('size_bytes'); $t->string('checksum')->nullable(); $t->unsignedBigInteger('uploaded_by'); $t->timestamps(); });
    $schema->create('service_prices', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('service_id'); $t->unsignedBigInteger('amount_clp'); $t->date('effective_from'); $t->date('effective_to')->nullable(); $t->timestamps(); });
    $now = now();
    DB::connection('center')->table('patients')->insert([['id'=>1,'first_name'=>'Paciente','last_name'=>'Uno','created_at'=>$now,'updated_at'=>$now], ['id'=>2,'first_name'=>'Paciente','last_name'=>'Dos','created_at'=>$now,'updated_at'=>$now]]);
    DB::connection('center')->table('professionals')->insert([['id'=>1,'first_name'=>'Profesional','last_name'=>'Uno','created_at'=>$now,'updated_at'=>$now], ['id'=>2,'first_name'=>'Profesional','last_name'=>'Dos','created_at'=>$now,'updated_at'=>$now]]);
    DB::connection('center')->table('services')->insert(['id'=>1,'name'=>'Consulta','created_at'=>$now,'updated_at'=>$now]);
    DB::connection('center')->table('result_types')->insert(['id'=>1,'name'=>'Informe','is_active'=>true,'created_at'=>$now,'updated_at'=>$now]);
    DB::connection('center')->table('appointments')->insert([
        ['id'=>1,'patient_id'=>1,'professional_id'=>1,'service_id'=>1,'appointment_date'=>'2026-10-20','status'=>'ATENDIDA','created_at'=>$now,'updated_at'=>$now],
        ['id'=>2,'patient_id'=>2,'professional_id'=>2,'service_id'=>1,'appointment_date'=>'2026-10-21','status'=>'CANCELADA','created_at'=>$now,'updated_at'=>$now],
    ]);
}
function resultsActor(string $slug, string $dbPath, string $role, ?int $patient = null, ?int $professional = null, ?MedicalCenter $center = null): array
{
    $center ??= MedicalCenter::create(['name'=>$slug,'slug'=>$slug,'database_name'=>$dbPath,'rut'=>$slug === 'results-isolated' ? '76123457-5' : '76123456-7','is_active'=>true]);
    $user = User::create(['name'=>$slug,'email'=>$slug.'@example.test','password'=>'Password123','is_active'=>true]);
    if ($role === 'PACIENTE' && $patient) {
        DB::connection('center')->table('patients')->where('id', $patient)
            ->update(['user_id' => $user->id, 'email' => $user->email]);
    }
    CenterUser::create(['medical_center_id'=>$center->id,'user_id'=>$user->id,'role'=>$role,'patient_id'=>$patient,'professional_id'=>$professional,'is_active'=>true]);
    return [$center, $user];
}
beforeEach(function () {
    $this->tenantFile = tempnam(sys_get_temp_dir(), 'medsync_results_');
    prepareResultsTenant($this->tenantFile);
    [$this->center, $this->admin] = resultsActor('results-admin', $this->tenantFile, 'ADMIN');
    [, $this->professional] = resultsActor('results-prof', $this->tenantFile, 'PROFESIONAL', null, 1, $this->center);
    [, $this->otherProfessional] = resultsActor('results-other-prof', $this->tenantFile, 'PROFESIONAL', null, 2, $this->center);
    [, $this->patient] = resultsActor('results-patient', $this->tenantFile, 'PACIENTE', 1, null, $this->center);
    [, $this->otherPatient] = resultsActor('results-other-patient', $this->tenantFile, 'PACIENTE', 2, null, $this->center);
    Storage::fake('local');
});
afterEach(function () {
    DB::purge('center');
    if (is_file($this->tenantFile)) unlink($this->tenantFile);
});
it('keeps a fictitious document private through draft, publication and voiding', function () {
    $session = ['active_medical_center_id'=>$this->center->id];
    $this->actingAs($this->professional, 'sanctum')->withHeader('Origin', 'http://localhost:3000')->withSession($session);
    $response = $this->post('/api/v1/results', ['appointment_id'=>1,'result_type_id'=>1,'performed_at'=>'2026-10-20','file'=>UploadedFile::fake()->createWithContent('ficticio.txt', 'Documento ficticio de prueba')]);
    $response->assertCreated();
    $id = $response->json('data.id'); $file = $response->json('data.files.0.id');
    $url = "/api/v1/results/{$id}/files/{$file}";
    $this->actingAs($this->patient, 'sanctum')->withSession($session)->getJson('/api/v1/results')->assertOk()->assertJsonCount(0, 'data');
    $this->get($url)->assertForbidden();
    $this->actingAs($this->otherProfessional, 'sanctum')->withSession($session)->postJson("/api/v1/results/{$id}/publish")->assertForbidden();
    $this->actingAs($this->professional, 'sanctum')->withSession($session)->postJson("/api/v1/results/{$id}/publish")->assertOk()->assertJsonPath('data.status', 'PUBLISHED');
    $this->actingAs($this->otherPatient, 'sanctum')->withSession($session)->get($url)->assertForbidden();
    $this->actingAs($this->patient, 'sanctum')->withSession($session)->getJson('/api/v1/results')->assertJsonCount(1, 'data');
    $this->get($url)->assertOk();
    $this->actingAs($this->professional, 'sanctum')->withSession($session)->postJson("/api/v1/results/{$id}/void", ['reason'=>'Error de documento'])->assertOk()->assertJsonPath('data.status', 'VOIDED');
    $this->actingAs($this->patient, 'sanctum')->withSession($session)->getJson('/api/v1/results')->assertJsonCount(0, 'data');
    $this->get($url)->assertForbidden();
});
it('recomputes historical estimated totals from prices and rejects overlapping periods', function () {
    $this->actingAs($this->admin, 'sanctum')->withHeader('Origin', 'http://localhost:3000')->withSession(['active_medical_center_id'=>$this->center->id]);
    $this->postJson('/api/v1/service-prices', ['service_id'=>1,'amount_clp'=>10000,'effective_from'=>'2026-10-01','effective_to'=>'2026-10-20'])->assertCreated();
    $active = $this->postJson('/api/v1/service-prices', ['service_id'=>1,'amount_clp'=>20000,'effective_from'=>'2026-10-21'])->assertCreated()->json('data.id');
    $this->postJson('/api/v1/service-prices', ['service_id'=>1,'amount_clp'=>30000,'effective_from'=>'2026-10-15'])->assertUnprocessable();
    $this->getJson('/api/v1/reports/summary?from=2026-10-01&to=2026-10-31')->assertOk()
        ->assertJsonPath('data.total_appointments', 2)
        ->assertJsonPath('data.estimated_revenue_clp', 10000)
        ->assertJsonPath('data.lost_estimate_clp', 20000);
    $this->patchJson('/api/v1/service-prices/'.$active, ['amount_clp'=>25000])->assertUnprocessable();
    $this->patchJson('/api/v1/service-prices/'.$active, ['effective_to'=>now()->toDateString()])->assertOk();
    $this->postJson('/api/v1/service-prices', ['service_id'=>1,'amount_clp'=>25000,'effective_from'=>now()->addDay()->toDateString()])->assertCreated();
    $this->getJson('/api/v1/reports/summary?from=2026-10-01&to=2026-10-31')->assertJsonPath('data.lost_estimate_clp', 20000);
    $this->actingAs($this->patient, 'sanctum')->withSession(['active_medical_center_id'=>$this->center->id])->getJson('/api/v1/reports/summary?from=2026-10-01&to=2026-10-31')->assertForbidden();
});
it('shows the price for the chosen appointment date without exposing price management', function () {
    $specialty = Specialty::create(['name' => 'Especialidad de prueba', 'is_active' => true]);
    MedicalCenterSpecialty::create(['medical_center_id' => $this->center->id, 'specialty_id' => $specialty->id, 'is_active' => true]);
    DB::connection('center')->table('services')->where('id', 1)->update(['specialty_id' => $specialty->id]);
    $this->actingAs($this->admin, 'sanctum')->withHeader('Origin', 'http://localhost:3000')->withSession(['active_medical_center_id' => $this->center->id]);
    $this->postJson('/api/v1/service-prices', ['service_id' => 1, 'amount_clp' => 10000, 'effective_from' => '2026-11-01', 'effective_to' => '2026-11-10'])->assertCreated();
    $this->postJson('/api/v1/service-prices', ['service_id' => 1, 'amount_clp' => 15000, 'effective_from' => '2026-11-11'])->assertCreated();

    $this->actingAs($this->patient, 'sanctum')->withSession(['active_medical_center_id' => $this->center->id]);
    $this->getJson('/api/v1/services/1/price?date=2026-11-05')->assertOk()->assertJsonPath('data.amount_clp', 10000);
    $this->getJson('/api/v1/services/1/price?date=2026-11-15')->assertOk()->assertJsonPath('data.amount_clp', 15000);
    $this->getJson('/api/v1/services/1/price?date=2026-10-31')->assertOk()->assertJsonPath('data.amount_clp', null);
    $this->getJson('/api/v1/service-prices')->assertForbidden();
});
it('does not include appointments or prices from another center database', function () {
    $this->actingAs($this->admin, 'sanctum')->withHeader('Origin', 'http://localhost:3000')->withSession(['active_medical_center_id'=>$this->center->id]);
    $this->postJson('/api/v1/service-prices', ['service_id'=>1,'amount_clp'=>15000,'effective_from'=>'2026-10-01'])->assertCreated();
    $this->getJson('/api/v1/reports/summary?from=2026-10-01&to=2026-10-31')->assertJsonPath('data.estimated_revenue_clp', 15000);
    $otherPath = tempnam(sys_get_temp_dir(), 'medsync_other_');
    try {
        prepareResultsTenant($otherPath);
        DB::connection('center')->table('appointments')->delete();
        [$otherCenter, $otherAdmin] = resultsActor('results-isolated', $otherPath, 'ADMIN');
        $this->actingAs($otherAdmin, 'sanctum')->withSession(['active_medical_center_id'=>$otherCenter->id])
            ->getJson('/api/v1/reports/summary?from=2026-10-01&to=2026-10-31')
            ->assertOk()->assertJsonPath('data.total_appointments', 0)->assertJsonPath('data.estimated_revenue_clp', 0);
        $this->getJson('/api/v1/service-prices')->assertOk()->assertJsonCount(0, 'data');
    } finally {
        DB::purge('center');
        if (is_file($otherPath)) unlink($otherPath);
    }
});
