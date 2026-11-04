<?php

use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function prepareRoleMatrixDatabase(string $connection, string $path, string $patientName): void
{
    config(["database.connections.{$connection}" => array_merge(
        config('database.connections.center'),
        ['driver' => 'sqlite', 'url' => null, 'database' => $path],
    )]);
    DB::purge($connection);
    Schema::connection($connection)->create('health_insurances', function (Blueprint $table) {
        $table->id(); $table->string('name'); $table->string('type');
        $table->boolean('is_active'); $table->timestamps();
    });
    Schema::connection($connection)->create('patients', function (Blueprint $table) {
        $table->id(); $table->unsignedBigInteger('user_id')->nullable();
        $table->unsignedBigInteger('health_insurance_id')->nullable();
        $table->string('first_name'); $table->string('last_name'); $table->string('rut');
        $table->date('birth_date')->nullable(); $table->string('email');
        $table->string('phone')->nullable(); $table->boolean('is_active');
        $table->timestamps(); $table->softDeletes();
    });
    Schema::connection($connection)->create('patient_addresses', function (Blueprint $table) {
        $table->id(); $table->unsignedBigInteger('patient_id'); $table->string('address_line');
        $table->string('commune')->nullable(); $table->string('region')->nullable();
        $table->boolean('is_primary'); $table->timestamps(); $table->softDeletes();
    });
    DB::connection($connection)->table('health_insurances')->insert([
        'id' => 1, 'name' => 'Fonasa', 'type' => 'FONASA', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::connection($connection)->table('patients')->insert([
        'id' => 1, 'first_name' => $patientName, 'last_name' => 'Prueba',
        'rut' => $connection === 'role_matrix_a' ? '11111111-1' : '22222222-2',
        'email' => strtolower($patientName).'@example.test',
        'phone' => '912345678', 'health_insurance_id' => 1, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('enforces role, ownership, inactive membership and tenant switching on HTTP routes', function () {
    $pathA = tempnam(sys_get_temp_dir(), 'roles-a-');
    $pathB = tempnam(sys_get_temp_dir(), 'roles-b-');
    try {
        prepareRoleMatrixDatabase('role_matrix_a', $pathA, 'Ana');
        prepareRoleMatrixDatabase('role_matrix_b', $pathB, 'Bea');
        $centerA = MedicalCenter::create(['name' => 'Centro A', 'slug' => 'roles-a', 'database_name' => $pathA, 'rut' => '76543210-3', 'is_active' => true]);
        $centerB = MedicalCenter::create(['name' => 'Centro B', 'slug' => 'roles-b', 'database_name' => $pathB, 'rut' => '76543211-1', 'is_active' => true]);
        $user = User::create(['name' => 'Cuenta compartida', 'email' => 'roles@example.test', 'password' => 'Password123', 'is_active' => true]);
        DB::connection('role_matrix_b')->table('patients')->where('id', 1)->update(['user_id' => $user->id, 'email' => $user->email]);
        $reception = CenterUser::create(['medical_center_id' => $centerA->id, 'user_id' => $user->id, 'role' => 'RECEPCIONISTA', 'is_active' => true]);
        CenterUser::create(['medical_center_id' => $centerB->id, 'user_id' => $user->id, 'role' => 'PACIENTE', 'patient_id' => 1, 'is_active' => true]);
        $client = $this->withHeader('Origin', 'http://localhost:3000')->actingAs($user, 'sanctum');

        $client->withSession(['active_medical_center_id' => $centerA->id])
            ->getJson('/api/v1/patients')->assertOk()->assertJsonPath('data.0.first_name', 'Ana');
        $client->getJson('/api/v1/center/permissions')->assertForbidden();
        $client->postJson('/api/v1/center/receptionists', ['name' => 'Otra cuenta', 'email' => 'other@example.test'])->assertForbidden();

        $client->withSession(['active_medical_center_id' => $centerB->id])
            ->getJson('/api/v1/patients')->assertForbidden();
        $client->getJson('/api/v1/patients/1')->assertOk()->assertJsonPath('data.first_name', 'Bea');
        $client->getJson('/api/v1/patients/2')->assertForbidden();
        $client->getJson('/api/v1/center/permissions')->assertForbidden();

        $reception->update(['is_active' => false]);
        $client->withSession(['active_medical_center_id' => $centerA->id])
            ->getJson('/api/v1/patients')->assertForbidden();
        $client->withSession(['active_medical_center_id' => $centerB->id])
            ->getJson('/api/v1/patients/1')->assertOk();
    } finally {
        DB::purge('center'); DB::purge('role_matrix_a'); DB::purge('role_matrix_b');
        @unlink($pathA); @unlink($pathB);
    }
});
