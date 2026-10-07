<?php

use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function prepareRegistrationDatabase(string $connection, string $path): void
{
    config([
        "database.connections.{$connection}" => array_merge(
            config('database.connections.center'),
            ['driver' => 'sqlite', 'url' => null, 'database' => $path],
        ),
    ]);
    DB::purge($connection);

    Schema::connection($connection)->create('health_insurances', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('type');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
    Schema::connection($connection)->create('patients', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->unsignedBigInteger('health_insurance_id')->nullable();
        $table->string('first_name');
        $table->string('last_name');
        $table->string('rut')->unique();
        $table->date('birth_date')->nullable();
        $table->string('email')->nullable()->unique();
        $table->string('phone')->nullable();
        $table->string('medical_insurance')->nullable();
        $table->timestamp('consent_at')->nullable();
        $table->string('consent_version')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });
    DB::connection($connection)->table('health_insurances')->insert([
        'name' => 'Fonasa',
        'type' => 'FONASA',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('registra al paciente solo en la base clínica del centro seleccionado', function () {
    $databaseA = tempnam(sys_get_temp_dir(), 'medsync-register-a-');
    $databaseB = tempnam(sys_get_temp_dir(), 'medsync-register-b-');

    try {
        prepareRegistrationDatabase('registration_a', $databaseA);
        prepareRegistrationDatabase('registration_b', $databaseB);

        $centerA = MedicalCenter::create([
            'name' => 'Centro A', 'slug' => 'centro-a',
            'database_name' => $databaseA, 'rut' => '76543210-3', 'is_active' => true,
        ]);
        $centerB = MedicalCenter::create([
            'name' => 'Centro B', 'slug' => 'centro-b',
            'database_name' => $databaseB, 'rut' => '76543211-1', 'is_active' => true,
        ]);

        $this->postJson('/api/register', [
            'center_slug' => 'centro-b',
            'database_name' => $databaseA, // El payload no puede elegir la conexión.
            'first_name' => 'Juan', 'last_name' => 'Pérez',
            'rut' => '12.345.678-5', 'birth_date' => '1990-05-10',
            'email' => 'juan.b@example.com', 'phone' => '+56 9 1234 5678',
            'health_insurance' => 'Fonasa', 'password' => 'Password123',
            'password_confirmation' => 'Password123', 'consent' => true,
        ])->assertCreated()
            ->assertJsonPath('data.medical_center.slug', 'centro-b');

        expect(DB::connection('registration_a')->table('patients')->count())->toBe(0);
        expect(DB::connection('registration_b')->table('patients')->count())->toBe(1);
        expect(CenterUser::where('medical_center_id', $centerA->id)->count())->toBe(0);
        expect(CenterUser::where('medical_center_id', $centerB->id)->count())->toBe(1);
    } finally {
        DB::purge('center');
        DB::purge('registration_a');
        DB::purge('registration_b');
        @unlink($databaseA);
        @unlink($databaseB);
    }
});
