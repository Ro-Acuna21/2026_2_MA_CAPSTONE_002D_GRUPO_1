<?php

use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;

function prepareProfessionalManagementDatabase(string $path): void
{
    config(['database.connections.center' => array_merge(config('database.connections.center'), [
        'driver' => 'sqlite', 'url' => null, 'database' => $path,
    ])]);
    DB::purge('center');

    Schema::connection('center')->create('professionals', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('first_name');
        $table->string('last_name');
        $table->string('rut');
        $table->string('email');
        $table->string('phone');
        $table->text('description')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });
    Schema::connection('center')->create('specialties', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->text('description')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
    Schema::connection('center')->create('professional_specialty', function (Blueprint $table) {
        $table->unsignedBigInteger('professional_id');
        $table->unsignedBigInteger('specialty_id');
        $table->timestamps();
        $table->primary(['professional_id', 'specialty_id']);
    });

    DB::connection('center')->table('specialties')->insert([
        ['id' => 5, 'name' => 'Medicina general', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 6, 'name' => 'Cardiología', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
}

it('actualiza fichas y especialidades sin alterar credenciales ni membresías de profesionales vinculados', function () {
    $database = tempnam(sys_get_temp_dir(), 'professional-management-');

    try {
        prepareProfessionalManagementDatabase($database);
        $center = MedicalCenter::create([
            'name' => 'Centro de prueba', 'slug' => 'profesionales-'.uniqid(),
            'database_name' => $database, 'rut' => '76543210-3', 'is_active' => true,
        ]);
        $admin = User::create([
            'name' => 'Administración', 'email' => 'admin-prof@example.test',
            'password' => 'Password123', 'is_active' => true,
        ]);
        $professionalUser = User::create([
            'name' => 'Profesional con cuenta', 'email' => 'cuenta-prof@example.test',
            'password' => 'OriginalPassword123', 'is_active' => true,
        ]);
        CenterUser::create([
            'medical_center_id' => $center->id, 'user_id' => $admin->id,
            'role' => 'ADMIN', 'is_active' => true,
        ]);
        $membership = CenterUser::create([
            'medical_center_id' => $center->id, 'user_id' => $professionalUser->id,
            'role' => 'PROFESIONAL', 'professional_id' => 2, 'is_active' => true,
        ]);

        DB::connection('center')->table('professionals')->insert([
            ['id' => 1, 'user_id' => null, 'first_name' => 'Ana', 'last_name' => 'Sin cuenta', 'rut' => '11111111-1', 'email' => 'ana@example.test', 'phone' => '+56911111111', 'description' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'user_id' => $professionalUser->id, 'first_name' => 'Bruno', 'last_name' => 'Vinculado', 'rut' => '22222222-2', 'email' => 'cuenta-prof@example.test', 'phone' => '+56922222222', 'description' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::connection('center')->table('professional_specialty')->insert([
            ['professional_id' => 1, 'specialty_id' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['professional_id' => 2, 'specialty_id' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $client = $this->withHeader('Origin', 'http://localhost:3000')
            ->withSession(['active_medical_center_id' => $center->id])
            ->actingAs($admin, 'sanctum');

        $client->patchJson('/api/v1/professionals/1', [
            'first_name' => 'Ana María', 'last_name' => 'Actualizada',
            'email' => 'ana.actualizada@example.test', 'phone' => '+56 9 3333 3333',
            'description' => 'Atiende consultas generales.', 'specialty_ids' => [6],
        ])->assertOk()
            ->assertJsonPath('data.email', 'ana.actualizada@example.test')
            ->assertJsonPath('data.specialty_ids.0', 6)
            ->assertJsonPath('data.description', 'Atiende consultas generales.');

        expect(DB::connection('center')->table('professional_specialty')->where('professional_id', 1)->pluck('specialty_id')->all())
            ->toBe([6]);
        expect(DB::connection('center')->table('professionals')->where('id', 1)->value('phone'))
            ->toBe('+56933333333');

        $client->patchJson('/api/v1/professionals/2', ['email' => 'cambio@example.test'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $client->patchJson('/api/v1/professionals/2', ['is_active' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('is_active');

        $client->patchJson('/api/v1/professionals/2', [
            'first_name' => 'Bruno actualizado', 'phone' => '+56944444444',
            'email' => 'cuenta-prof@example.test', 'is_active' => true,
            'specialty_ids' => [6],
        ])->assertOk()->assertJsonPath('data.user_id', $professionalUser->id);

        $professionalUser->refresh();
        expect($professionalUser->email)->toBe('cuenta-prof@example.test')
            ->and(Hash::check('OriginalPassword123', $professionalUser->password))->toBeTrue()
            ->and(DB::connection('center')->table('professionals')->where('id', 2)->value('user_id'))->toBe($professionalUser->id)
            ->and(CenterUser::findOrFail($membership->id)->only(['user_id', 'role', 'professional_id', 'is_active']))
                ->toBe($membership->only(['user_id', 'role', 'professional_id', 'is_active']));
    } finally {
        DB::purge('center');
        @unlink($database);
    }
});
