<?php

use App\Models\Center\HealthInsurance;
use App\Models\Center\Patient;
use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Models\User;

beforeEach(function () {
    $this->center = MedicalCenter::create([
        'name' => 'Centro de prueba', 'slug' => 'centro-prueba',
        'rut' => '76543210-3', 'database_name' => config('database.connections.center.database'),
        'is_active' => true,
    ]);
    $this->user = User::create([
        'name' => 'Paciente existente', 'email' => 'existing@example.test',
        'password' => 'Password123', 'is_active' => true,
    ]);
    HealthInsurance::create(['name' => 'Fonasa', 'type' => 'FONASA', 'is_active' => true]);
});

function enrollmentPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Paciente', 'last_name' => 'Existente', 'rut' => '12.345.678-5',
        'birth_date' => '1990-05-10', 'phone' => '+56 9 1234 5678',
        'health_insurance' => 'Fonasa', 'consent' => true,
    ], $overrides);
}

it('creates one local patient and membership only after explicit confirmation', function () {
    $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/login', [
        'email' => $this->user->email, 'password' => 'Password123', 'center_slug' => $this->center->slug,
    ])->assertStatus(409)->assertJsonPath('code', 'CENTER_ENROLLMENT_REQUIRED');

    expect(Patient::count())->toBe(0);
    expect(CenterUser::count())->toBe(0);

    $this->postJson('/api/v1/patient-enrollment', enrollmentPayload())
        ->assertCreated()->assertJsonPath('data.center.slug', 'centro-prueba');

    expect(Patient::count())->toBe(1);
    $patient = Patient::firstOrFail();
    expect($patient->user_id)->toBe($this->user->id);
    expect($patient->email)->toBe($this->user->email);
    expect(CenterUser::where('medical_center_id', $this->center->id)->where('user_id', $this->user->id)
        ->where('patient_id', $patient->id)->where('role', 'PACIENTE')->count())->toBe(1);

    $this->postJson('/api/v1/patient-enrollment', enrollmentPayload())->assertOk();
    expect(Patient::count())->toBe(1);
    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.patient.id', $patient->id);
});

it('rejects duplicate local identities and never links them without verification', function () {
    Patient::create([
        'first_name' => 'Otra', 'last_name' => 'Persona', 'rut' => '12345678-5',
        'email' => 'other@example.test', 'phone' => '912345678', 'birth_date' => '1990-05-10',
        'health_insurance_id' => HealthInsurance::firstOrFail()->id,
        'is_active' => true,
    ]);
    $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/login', [
        'email' => $this->user->email, 'password' => 'Password123', 'center_slug' => $this->center->slug,
    ])->assertStatus(409);
    $this->postJson('/api/v1/patient-enrollment', enrollmentPayload())
        ->assertUnprocessable()->assertJsonValidationErrors('rut');
    expect(CenterUser::count())->toBe(0);
    expect(Patient::count())->toBe(1);
});

it('rejects enrollment without an authenticated pending center', function () {
    $this->postJson('/api/v1/patient-enrollment', enrollmentPayload())->assertUnauthorized();
    $this->actingAs($this->user)->withHeader('Origin', 'http://localhost:3000')
        ->postJson('/api/v1/patient-enrollment', enrollmentPayload())
        ->assertForbidden();
    expect(Patient::count())->toBe(0);
});

it('repairs an orphan membership without duplicating the global account or membership', function () {
    $membership = CenterUser::create([
        'medical_center_id' => $this->center->id, 'user_id' => $this->user->id,
        'role' => 'PACIENTE', 'patient_id' => 987654, 'is_active' => true,
    ]);
    $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/login', [
        'email' => $this->user->email, 'password' => 'Password123', 'center_slug' => $this->center->slug,
    ])->assertStatus(409)->assertJsonPath('code', 'CENTER_ENROLLMENT_REQUIRED');
    $this->getJson('/api/v1/me')->assertForbidden();
    $this->postJson('/api/v1/patient-enrollment', enrollmentPayload())->assertCreated();
    expect(CenterUser::count())->toBe(1);
    expect(User::count())->toBe(1);
    expect(Patient::count())->toBe(1);
    expect($membership->fresh()->patient_id)->toBe(Patient::firstOrFail()->id);
    $this->getJson('/api/v1/me')->assertOk();
});

it('does not trust a reused patient id or link a ficha owned by someone else', function () {
    $other = User::create(['name' => 'Otra', 'email' => 'other@example.test', 'password' => 'Password123', 'is_active' => true]);
    $patient = Patient::create([
        'user_id' => $other->id, 'first_name' => 'Otra', 'last_name' => 'Persona',
        'rut' => '12345678-5', 'email' => $other->email, 'phone' => '912345678',
        'birth_date' => '1990-05-10', 'health_insurance_id' => HealthInsurance::firstOrFail()->id,
        'is_active' => true,
    ]);
    $membership = CenterUser::create([
        'medical_center_id' => $this->center->id, 'user_id' => $this->user->id,
        'role' => 'PACIENTE', 'patient_id' => $patient->id, 'is_active' => true,
    ]);
    $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/login', [
        'email' => $this->user->email, 'password' => 'Password123', 'center_slug' => $this->center->slug,
    ])->assertStatus(409);
    $this->postJson('/api/v1/patient-enrollment', enrollmentPayload())
        ->assertUnprocessable()->assertJsonValidationErrors('rut');
    expect($membership->fresh()->patient_id)->toBe($patient->id);
    expect(Patient::count())->toBe(1);
});

it('refuses to reactivate an inactive membership', function () {
    CenterUser::create([
        'medical_center_id' => $this->center->id, 'user_id' => $this->user->id,
        'role' => 'PACIENTE', 'patient_id' => 999, 'is_active' => false,
    ]);
    $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/login', [
        'email' => $this->user->email, 'password' => 'Password123', 'center_slug' => $this->center->slug,
    ])->assertUnprocessable();
    expect(Patient::count())->toBe(0);
});

it('reuses a verified local patient left after a partial Core failure', function () {
    $patient = Patient::create([
        'user_id' => $this->user->id, 'first_name' => 'Paciente', 'last_name' => 'Existente',
        'rut' => '12345678-5', 'email' => $this->user->email, 'phone' => '912345678',
        'birth_date' => '1990-05-10', 'health_insurance_id' => HealthInsurance::firstOrFail()->id,
        'is_active' => true,
    ]);
    $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/login', [
        'email' => $this->user->email, 'password' => 'Password123', 'center_slug' => $this->center->slug,
    ])->assertStatus(409);
    $this->postJson('/api/v1/patient-enrollment', enrollmentPayload())
        ->assertOk()->assertJsonPath('data.patient_id', $patient->id);
    expect(Patient::count())->toBe(1);
    expect(CenterUser::count())->toBe(1);
});
