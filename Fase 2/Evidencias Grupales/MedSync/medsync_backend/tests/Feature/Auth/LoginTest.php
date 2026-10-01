<?php

use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Models\User;

beforeEach(function () {
    $this->center = MedicalCenter::create([
        'name' => 'Clínica Horizonte',
        'slug' => 'clinica-horizonte',
        'rut' => '76543210-3',
        'database_name' => config('database.connections.center.database'),
        'is_active' => true,
    ]);

    $this->user = User::create([
        'name' => 'Ana Soto',
        'email' => 'ana.soto@example.com',
        'password' => 'password123',
        'is_active' => true,
    ]);

    CenterUser::create([
        'medical_center_id' => $this->center->id,
        'user_id' => $this->user->id,
        'role' => 'PACIENTE',
        'is_active' => true,
    ]);
});

it('permite iniciar sesion con credenciales correctas', function () {
    $response = $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/login', [
        'email' => 'ana.soto@example.com',
        'password' => 'password123',
        'center_slug' => 'clinica-horizonte',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.email', 'ana.soto@example.com')
        ->assertJsonPath('data.role', 'PACIENTE')
        ->assertJsonPath('data.medical_center.slug', 'clinica-horizonte');

    $this->assertAuthenticatedAs($this->user);
});

it('rechaza el login con contrasena incorrecta', function () {
    $response = $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/login', [
        'email' => 'ana.soto@example.com',
        'password' => 'incorrecta',
        'center_slug' => 'clinica-horizonte',
    ]);

    $response->assertUnprocessable();
    $this->assertGuest();
});

it('permite consultar el usuario autenticado en /api/v1/me dentro de su centro activo', function () {
    $response = $this->withHeader('Origin', 'http://localhost:3000')->withSession([
        'active_medical_center_id' => $this->center->id,
    ])->actingAs($this->user)->getJson('/api/v1/me');

    $response->assertOk()
        ->assertJsonPath('data.role', 'PACIENTE')
        ->assertJsonPath('data.email', 'ana.soto@example.com');
});

it('cierra la sesion correctamente', function () {
    $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/login', [
        'email' => 'ana.soto@example.com',
        'password' => 'password123',
        'center_slug' => 'clinica-horizonte',
    ])->assertOk();

    $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/logout')
        ->assertOk();

    $this->assertGuest('web');
});

it('rechaza credenciales correctas cuando el usuario no pertenece al centro solicitado', function () {
    MedicalCenter::create([
        'name' => 'Centro Médico Alameda',
        'slug' => 'centro-medico-alameda',
        'rut' => '76543211-1',
        'database_name' => 'medsync_centro_medico_alameda',
        'is_active' => true,
    ]);

    $this->postJson('/api/login', [
        'email' => 'ana.soto@example.com',
        'password' => 'password123',
        'center_slug' => 'centro-medico-alameda',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('center_slug');

    $this->assertGuest();
});

it('rechaza un centro inexistente o inactivo antes de autenticar el acceso clínico', function (string $slug) {
    if ($slug === 'centro-inactivo') {
        MedicalCenter::create([
            'name' => 'Centro Inactivo',
            'slug' => $slug,
            'rut' => '76543212-K',
            'database_name' => 'medsync_centro_inactivo',
            'is_active' => false,
        ]);
    }

    $this->postJson('/api/login', [
        'email' => 'ana.soto@example.com',
        'password' => 'password123',
        'center_slug' => $slug,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('center_slug');

    $this->assertGuest();
})->with(['centro-inexistente', 'centro-inactivo']);

it('mantiene al SUPER_ADMIN separado del login clínico', function () {
    $superAdmin = User::create([
        'name' => 'Admin MedSync',
        'email' => 'admin@medsync.cl',
        'password' => 'Password123',
        'system_role' => 'SUPER_ADMIN',
        'is_active' => true,
    ]);

    $this->postJson('/api/login', [
        'email' => $superAdmin->email,
        'password' => 'Password123',
        'center_slug' => 'clinica-horizonte',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('center_slug');

    $this->assertGuest();

    $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/login', [
        'email' => $superAdmin->email,
        'password' => 'Password123',
    ])->assertOk()
        ->assertJsonPath('data.role', 'SUPER_ADMIN')
        ->assertJsonPath('data.medical_center', null);
});
