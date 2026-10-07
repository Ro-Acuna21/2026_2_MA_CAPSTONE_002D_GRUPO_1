<?php

use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function preparePatientDatabase(string $connection, string $path): void
{
    config(["database.connections.{$connection}" => array_merge(config('database.connections.center'), [
        'driver' => 'sqlite', 'url' => null, 'database' => $path,
    ])]);
    DB::purge($connection);
    Schema::connection($connection)->create('health_insurances', function (Blueprint $table) {
        $table->id(); $table->string('name'); $table->string('type');
        $table->boolean('is_active'); $table->timestamps();
    });
    Schema::connection($connection)->create('patients', function (Blueprint $table) {
        $table->id(); $table->unsignedBigInteger('user_id')->nullable();
        $table->unsignedBigInteger('health_insurance_id')->nullable();
        $table->string('first_name'); $table->string('last_name'); $table->string('rut')->unique();
        $table->date('birth_date')->nullable(); $table->string('email')->unique();
        $table->string('phone')->nullable(); $table->string('medical_insurance')->nullable();
        $table->timestamp('consent_at')->nullable(); $table->string('consent_version')->nullable();
        $table->boolean('is_active'); $table->timestamps(); $table->softDeletes();
    });
    Schema::connection($connection)->create('patient_addresses', function (Blueprint $table) {
        $table->id(); $table->unsignedBigInteger('patient_id'); $table->string('address_line');
        $table->string('commune')->nullable(); $table->string('region')->nullable();
        $table->string('postal_code')->nullable(); $table->string('reference')->nullable();
        $table->boolean('is_primary'); $table->timestamps(); $table->softDeletes();
    });
    DB::connection($connection)->table('health_insurances')->insert([
        'name' => 'Fonasa', 'type' => 'FONASA', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function patientFixture(string $name, string $rut, string $email, int $id): array
{
    return [
        'id' => $id, 'first_name' => $name, 'last_name' => 'Prueba',
        'rut' => $rut, 'birth_date' => '1990-01-01', 'email' => $email,
        'phone' => '+56912345678', 'health_insurance_id' => 1,
        'consent_at' => now(), 'consent_version' => 'v1', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ];
}

it('persiste CRUD de pacientes en el tenant activo y conserva el listado usado por Reservas', function () {
    $pathA = tempnam(sys_get_temp_dir(), 'patient-a-');
    $pathB = tempnam(sys_get_temp_dir(), 'patient-b-');
    try {
        preparePatientDatabase('patient_a', $pathA);
        preparePatientDatabase('patient_b', $pathB);
        DB::connection('patient_a')->table('patients')->insert(patientFixture('Ana', '11111111-1', 'ana@example.com', 1));
        DB::connection('patient_b')->table('patients')->insert(patientFixture('Bea', '22222222-2', 'bea@example.com', 2));
        $centerA = MedicalCenter::create(['name' => 'Centro A', 'slug' => 'pacientes-a', 'database_name' => $pathA, 'rut' => '76543210-3', 'is_active' => true]);
        MedicalCenter::create(['name' => 'Centro B', 'slug' => 'pacientes-b', 'database_name' => $pathB, 'rut' => '76543211-1', 'is_active' => true]);
        $receptionist = User::create(['name' => 'Recepción A', 'email' => 'recepcion.a@example.com', 'password' => 'Password123', 'is_active' => true]);
        CenterUser::create(['medical_center_id' => $centerA->id, 'user_id' => $receptionist->id, 'role' => 'RECEPCIONISTA', 'is_active' => true]);
        $client = $this->withHeader('Origin', 'http://localhost:3000')->withSession(['active_medical_center_id' => $centerA->id])->actingAs($receptionist, 'sanctum');

        $client->getJson('/api/v1/patients')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', 'Ana')->assertJsonPath('data.0.rut', '11111111-1');
        $client->getJson('/api/v1/patients/1')->assertOk()->assertJsonPath('data.email', 'ana@example.com');
        $client->getJson('/api/v1/patients/2')->assertNotFound();
        $client->patchJson('/api/v1/patients/2', ['phone' => '+56911111111'])->assertNotFound();

        $payload = [
            'first_name' => 'Carla', 'last_name' => 'Paciente', 'rut' => '12.345.678-5',
            'birth_date' => '1995-02-03', 'email' => 'carla@example.com',
            'phone' => '+56 9 1234 5678', 'health_insurance' => 'Fonasa',
            'address' => 'Calle 1', 'consent' => true,
            'database_name' => $pathB,
        ];
        $client->postJson('/api/v1/patients', $payload)->assertCreated()
            ->assertJsonPath('data.email', 'carla@example.com')
            ->assertJsonPath('data.address', 'Calle 1');
        $id = DB::connection('patient_a')->table('patients')->where('email', 'carla@example.com')->value('id');
        expect(DB::connection('patient_b')->table('patients')->count())->toBe(1);
        $client->patchJson("/api/v1/patients/{$id}", ['phone' => '+56 9 8765 4321', 'address' => 'Calle 2'])
            ->assertOk()->assertJsonPath('data.address', 'Calle 2');
        expect(DB::connection('patient_a')->table('patients')->where('id', $id)->value('phone'))->toBe('+56987654321');
        $client->getJson("/api/v1/patients/{$id}")->assertOk()->assertJsonPath('data.address', 'Calle 2');
        $client->getJson('/api/v1/patients')->assertOk()->assertJsonCount(2, 'data');

        $client->postJson('/api/v1/patients', $payload)->assertUnprocessable()->assertJsonValidationErrors('rut');
        $client->postJson('/api/v1/patients', array_merge($payload, ['rut' => 'invalido']))->assertUnprocessable()->assertJsonValidationErrors('rut');
        $client->patchJson("/api/v1/patients/{$id}", ['phone' => 'invalido'])->assertUnprocessable()->assertJsonValidationErrors('phone');
    } finally {
        DB::purge('center'); DB::purge('patient_a'); DB::purge('patient_b');
        @unlink($pathA); @unlink($pathB);
    }
});

it('restringe el acceso a recepción y permite al paciente editar solo su propia ficha', function () {
    $path = tempnam(sys_get_temp_dir(), 'patient-own-');
    try {
        preparePatientDatabase('patient_own', $path);
        DB::connection('patient_own')->table('patients')->insert([
            patientFixture('Ana', '11111111-1', 'ana@example.com', 1),
            patientFixture('Bea', '22222222-2', 'bea@example.com', 2),
        ]);
        $center = MedicalCenter::create(['name' => 'Centro A', 'slug' => 'pacientes-own', 'database_name' => $path, 'rut' => '76543210-3', 'is_active' => true]);
        $patient = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'Password123', 'is_active' => true]);
        CenterUser::create(['medical_center_id' => $center->id, 'user_id' => $patient->id, 'role' => 'PACIENTE', 'patient_id' => 1, 'is_active' => true]);
        $this->getJson('/api/v1/patients')->assertUnauthorized();
        $client = $this->withHeader('Origin', 'http://localhost:3000')->withSession(['active_medical_center_id' => $center->id])->actingAs($patient, 'sanctum');
        $client->getJson('/api/v1/patients')->assertForbidden();
        $client->postJson('/api/v1/patients', [])->assertForbidden();
        $client->getJson('/api/v1/patients/2')->assertForbidden();
        $client->patchJson('/api/v1/patients/2', ['phone' => '+56912345678'])->assertForbidden();
        $client->getJson('/api/v1/patients/1')->assertOk()->assertJsonPath('data.first_name', 'Ana');
        $client->patchJson('/api/v1/patients/1', ['email' => 'otro@example.com'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $client->patchJson('/api/v1/patients/1', ['first_name' => 'Anita'])
            ->assertOk()->assertJsonPath('data.first_name', 'Anita')->assertJsonPath('data.email', 'ana@example.com');
        expect(DB::connection('patient_own')->table('patients')->where('id', 1)->value('first_name'))->toBe('Anita');
    } finally {
        DB::purge('center'); DB::purge('patient_own'); @unlink($path);
    }
});
