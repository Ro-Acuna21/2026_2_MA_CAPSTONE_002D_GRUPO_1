<?php

use App\Models\Center\HealthInsurance;
use App\Models\Center\Patient;
use App\Models\Core\MedicalCenter;
use App\Models\User;

beforeEach(function () {
    $this->center = MedicalCenter::create([
        'name' => 'Clínica Horizonte',
        'slug' => 'clinica-horizonte',
        'rut' => '76543210-3',
        'database_name' => ':memory:',
        'is_active' => true,
    ]);

    HealthInsurance::create(['name' => 'Fonasa', 'type' => 'FONASA', 'is_active' => true]);
});

function validRegisterPayload(array $overrides = []): array
{
    return array_merge([
        'center_slug' => 'clinica-horizonte',
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

    $response->assertCreated()
        ->assertJsonPath('data.role', 'PACIENTE')
        ->assertJsonPath('data.medical_center.slug', 'clinica-horizonte')
        ->assertJsonMissingPath('data.medical_center.database_name');

    expect(User::where('email', 'juan.perez@example.com')->exists())->toBeTrue();
    expect(
        Patient::where('rut', '12345678-5')->exists()
    )->toBeTrue();
});

it('publica las 16 regiones y las 346 comunas del catálogo CUT de SUBDERE', function () {
    $this->getJson('/api/public/territorial-catalog')
        ->assertOk()
        ->assertJsonCount(16, 'data')
        ->assertJsonPath('data.0.code', '15')
        ->assertJsonPath('data.0.name', 'Arica y Parinacota')
        ->assertJsonPath('data.6.name', 'Metropolitana de Santiago');

    $catalog = app(\App\Services\TerritorialCatalog::class);
    expect(array_sum(array_map(fn ($region) => count($region['communes']), $catalog->regions())))->toBe(346);
});

it('guarda nombres canónicos cuando el registro envía códigos CUT válidos', function () {
    $response = $this->postJson('/api/register', validRegisterPayload([
        'address' => 'Calle de prueba 123',
        'region_code' => '13',
        'commune_code' => '13119',
    ]));

    $response->assertCreated();
    $patientId = Patient::where('rut', '12345678-5')->value('id');
    $address = \App\Models\Center\PatientAddress::where('patient_id', $patientId)->firstOrFail();
    expect($address->region)->toBe('Metropolitana de Santiago')
        ->and($address->commune)->toBe('Maipú')
        ->and($address->address_line)->toBe('Calle de prueba 123');
});

it('rechaza una comuna CUT que no pertenece a la región seleccionada', function () {
    $this->postJson('/api/register', validRegisterPayload([
        'email' => 'juan.territorio@example.com',
        'address' => 'Calle de prueba 123',
        'region_code' => '13',
        'commune_code' => '01101',
    ]))->assertUnprocessable()->assertJsonValidationErrors('commune_code');

    expect(User::where('email', 'juan.territorio@example.com')->exists())->toBeFalse();
});

it('rechaza un centro inexistente o inactivo sin crear cuentas', function (string $slug) {
    $this->center->update(['is_active' => false]);

    $this->postJson('/api/register', validRegisterPayload(['center_slug' => $slug]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('center_slug');

    expect(User::where('email', 'juan.perez@example.com')->exists())->toBeFalse();
    expect(Patient::where('rut', '12345678-5')->exists())->toBeFalse();
})->with(['centro-inexistente', 'clinica-horizonte']);

it('exige la selección explícita del centro', function () {
    $payload = validRegisterPayload();
    unset($payload['center_slug']);

    $this->postJson('/api/register', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('center_slug');
});

it('publica únicamente los centros activos sin exponer el nombre de su base', function () {
    MedicalCenter::create([
        'name' => 'Centro suspendido',
        'slug' => 'centro-suspendido',
        'rut' => '76543211-1',
        'database_name' => 'otra_base',
        'is_active' => false,
    ]);

    $this->getJson('/api/public/centers')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'clinica-horizonte')
        ->assertJsonMissingPath('data.0.database_name');
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
