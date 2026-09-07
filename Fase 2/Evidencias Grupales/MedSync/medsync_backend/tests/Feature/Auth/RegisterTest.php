<?php

use App\Models\HealthInsurance;
use App\Models\MedicalCenter;
use App\Models\Patient;
use App\Models\User;

beforeEach(function () {
    $this->center = MedicalCenter::create([
        'name' => 'Clínica Horizonte',
        'slug' => 'clinica-horizonte',
        'rut' => '76543210-3',
        'is_active' => true,
    ]);

    HealthInsurance::create(['name' => 'Fonasa', 'type' => 'FONASA', 'is_active' => true]);
});

function validRegisterPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'rut' => '12.345.678-5',
        'birth_date' => '1990-05-10',
        'email' => 'juan.perez@example.com',
        'phone' => '+56 9 1234 5678',
        'health_insurance' => 'Fonasa',
        'medical_insurance' => null,
        'address' => null,
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'consent' => true,
    ], $overrides);
}

it('registra un paciente nuevo correctamente', function () {
    $response = $this->postJson('/api/register', validRegisterPayload());

    $response->assertCreated()->assertJsonPath('data.role', 'PACIENTE');

    expect(User::where('email', 'juan.perez@example.com')->exists())->toBeTrue();
    expect(
        Patient::where('rut', '12345678-5')
            ->where('medical_center_id', $this->center->id)
            ->exists()
    )->toBeTrue();
});

it('rechaza un rut chileno invalido', function () {
    $response = $this->postJson('/api/register', validRegisterPayload([
        'rut' => '12.345.678-9', // dígito verificador incorrecto (debería ser 5)
        'email' => 'juan.invalido@example.com',
    ]));

    $response->assertUnprocessable()->assertJsonValidationErrors('rut');
});

it('rechaza un correo ya registrado en el mismo centro', function () {
    $existingUser = User::create([
        'name' => 'Existente',
        'email' => 'repetido@example.com',
        'password' => 'password123',
        'is_active' => true,
    ]);

    Patient::create([
        'medical_center_id' => $this->center->id,
        'user_id' => $existingUser->id,
        'first_name' => 'Existente',
        'last_name' => 'Usuario',
        'rut' => '11111111-1',
        'email' => 'repetido@example.com',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/register', validRegisterPayload([
        'rut' => '22222222-2',
        'email' => 'repetido@example.com',
    ]));

    $response->assertUnprocessable();
});
