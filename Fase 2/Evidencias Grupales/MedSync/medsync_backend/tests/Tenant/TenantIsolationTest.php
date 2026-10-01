<?php

use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function createTenantProfessionalsDatabase(string $connection, string $path, string $firstName): void
{
    config([
        "database.connections.{$connection}" => array_merge(
            config('database.connections.center'),
            ['driver' => 'sqlite', 'url' => null, 'database' => $path],
        ),
    ]);

    DB::purge($connection);

    Schema::connection($connection)->create('professionals', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('first_name');
        $table->string('last_name');
        $table->string('rut');
        $table->string('email');
        $table->string('phone');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });

    DB::connection($connection)->table('professionals')->insert([
        'first_name' => $firstName,
        'last_name' => 'Exclusivo',
        'rut' => $connection === 'tenant_a' ? '11111111-1' : '22222222-2',
        'email' => strtolower($firstName).'@example.com',
        'phone' => '+56912345678',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('aísla las consultas clínicas en bases SQLite distintas según el centro activo', function () {
    $databaseA = tempnam(sys_get_temp_dir(), 'medsync-tenant-a-');
    $databaseB = tempnam(sys_get_temp_dir(), 'medsync-tenant-b-');

    try {
        createTenantProfessionalsDatabase('tenant_a', $databaseA, 'Profesional A');
        createTenantProfessionalsDatabase('tenant_b', $databaseB, 'Profesional B');

        $centerA = MedicalCenter::create(['name' => 'Centro A', 'slug' => 'centro-a', 'database_name' => $databaseA, 'rut' => '76543210-3', 'is_active' => true]);
        $centerB = MedicalCenter::create(['name' => 'Centro B', 'slug' => 'centro-b', 'database_name' => $databaseB, 'rut' => '76543211-1', 'is_active' => true]);
        $userA = User::create(['name' => 'Admin A', 'email' => 'admin.a@example.com', 'password' => 'Password123', 'is_active' => true]);
        $userB = User::create(['name' => 'Admin B', 'email' => 'admin.b@example.com', 'password' => 'Password123', 'is_active' => true]);

        CenterUser::create(['medical_center_id' => $centerA->id, 'user_id' => $userA->id, 'role' => 'ADMIN', 'is_active' => true]);
        CenterUser::create(['medical_center_id' => $centerB->id, 'user_id' => $userB->id, 'role' => 'ADMIN', 'is_active' => true]);

        $this->withHeader('Origin', 'http://localhost:3000')->withSession(['active_medical_center_id' => $centerA->id])->actingAs($userA, 'sanctum')->getJson('/api/v1/professionals')
            ->assertOk()->assertJsonPath('data.0.first_name', 'Profesional A');

        $this->withHeader('Origin', 'http://localhost:3000')->withSession(['active_medical_center_id' => $centerB->id])->actingAs($userB, 'sanctum')->getJson('/api/v1/professionals')
            ->assertOk()->assertJsonPath('data.0.first_name', 'Profesional B');

        expect(DB::connection('center')->getDatabaseName())->toBe($databaseB);
    } finally {
        DB::purge('center');
        DB::purge('tenant_a');
        DB::purge('tenant_b');
        @unlink($databaseA);
        @unlink($databaseB);
    }
});

it('bloquea peticiones clínicas cuando el centro se desactiva después del login', function () {
    $database = tempnam(sys_get_temp_dir(), 'medsync-inactive-');

    try {
        $center = MedicalCenter::create(['name' => 'Centro suspendido', 'slug' => 'centro-suspendido', 'database_name' => $database, 'rut' => '76543212-K', 'is_active' => false]);
        $user = User::create(['name' => 'Admin suspendido', 'email' => 'admin.suspendido@example.com', 'password' => 'Password123', 'is_active' => true]);
        CenterUser::create(['medical_center_id' => $center->id, 'user_id' => $user->id, 'role' => 'ADMIN', 'is_active' => true]);

        $this->withHeader('Origin', 'http://localhost:3000')->withSession(['active_medical_center_id' => $center->id])->actingAs($user)->getJson('/api/v1/professionals')
            ->assertForbidden();
    } finally {
        @unlink($database);
    }
});
