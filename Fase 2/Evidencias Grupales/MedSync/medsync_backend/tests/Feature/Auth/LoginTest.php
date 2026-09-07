<?php

use App\Models\CenterUser;
use App\Models\MedicalCenter;
use App\Models\User;

beforeEach(function () {
    $this->center = MedicalCenter::create([
        'name' => 'Clínica Horizonte',
        'slug' => 'clinica-horizonte',
        'rut' => '76543210-3',
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
    $response = $this->postJson('/api/login', [
        'email' => 'ana.soto@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.email', 'ana.soto@example.com')
        ->assertJsonPath('data.role', 'PACIENTE');

    $this->assertAuthenticatedAs($this->user);
});

it('rechaza el login con contrasena incorrecta', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'ana.soto@example.com',
        'password' => 'incorrecta',
    ]);

    $response->assertUnprocessable();
    $this->assertGuest();
});

it('permite consultar el usuario autenticado en /api/v1/me', function () {
    $response = $this->actingAs($this->user)->getJson('/api/v1/me');

    $response->assertOk()
        ->assertJsonPath('data.role', 'PACIENTE')
        ->assertJsonPath('data.email', 'ana.soto@example.com');
});

it('cierra la sesion correctamente', function () {
    $this->actingAs($this->user)
        ->postJson('/api/logout')
        ->assertOk();

    $this->assertGuest();
});
