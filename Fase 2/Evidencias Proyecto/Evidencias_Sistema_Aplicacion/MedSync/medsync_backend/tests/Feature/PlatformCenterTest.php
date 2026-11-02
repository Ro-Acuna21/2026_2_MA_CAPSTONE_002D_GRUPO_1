<?php

use App\Models\Core\MedicalCenter;
use App\Models\User;
use App\Services\Tenants\CenterProvisioner;

beforeEach(function () {
    $this->platformUser = User::create([
        'name' => 'Plataforma', 'email' => 'platform@example.test',
        'password' => 'Password123', 'system_role' => 'SUPER_ADMIN', 'is_active' => true,
    ]);
});

it('creates only a provisioned center and publishes it after success', function () {
    app()->bind(CenterProvisioner::class, fn () => new class extends CenterProvisioner {
        public function provision(MedicalCenter $center, bool $activate = true): void
        {
            $center->update(['provisioning_status' => 'READY', 'is_active' => $activate, 'provisioned_at' => now()]);
        }
    });

    $this->actingAs($this->platformUser)->postJson('/api/v1/platform/centers', [
        'name' => 'Centro Nuevo', 'slug' => 'centro-nuevo', 'rut' => '12.345.678-5',
        'public_description' => 'Atención de prueba para la comunidad.',
        'address_line' => 'Calle Ficticia 123', 'commune' => 'Santiago', 'region' => 'Metropolitana',
        'plan' => 'STARTER', 'subscription_status' => 'TRIAL', 'is_active' => true,
    ])->assertCreated()->assertJsonPath('data.provisioning_status', 'READY');

    $center = MedicalCenter::where('slug', 'centro-nuevo')->firstOrFail();
    expect($center->database_name)->toMatch('/^medsync_c_[a-z0-9]+$/');
    $this->getJson('/api/public/centers')->assertOk()->assertJsonFragment(['slug' => 'centro-nuevo'])
        ->assertJsonPath('data.0.address', 'Calle Ficticia 123')
        ->assertJsonPath('data.0.description', 'Atención de prueba para la comunidad.');
    $this->postJson('/api/v1/platform/centers', [
        'name' => 'Centro Repetido', 'slug' => 'centro-nuevo', 'rut' => '12.345.678-5',
        'plan' => 'STARTER', 'subscription_status' => 'TRIAL', 'is_active' => true,
    ])->assertUnprocessable();
});

it('keeps a failed center unavailable and permits retry on the same record', function () {
    $provisioner = new class extends CenterProvisioner {
        public bool $fail = true;
        public function provision(MedicalCenter $center, bool $activate = true): void
        {
            if ($this->fail) {
                $center->update(['provisioning_status' => 'FAILED', 'is_active' => false]);
                throw new RuntimeException('Prueba de fallo');
            }
            $center->update(['provisioning_status' => 'READY', 'is_active' => $activate, 'provisioned_at' => now()]);
        }
    };
    app()->instance(CenterProvisioner::class, $provisioner);

    $this->actingAs($this->platformUser)->postJson('/api/v1/platform/centers', [
        'name' => 'Centro Recuperable', 'slug' => 'centro-recuperable', 'rut' => '12.345.678-5',
        'plan' => 'PRO', 'subscription_status' => 'TRIAL', 'is_active' => true,
    ])->assertStatus(503)->assertJsonPath('data.provisioning_status', 'FAILED');

    $center = MedicalCenter::where('slug', 'centro-recuperable')->firstOrFail();
    $this->getJson('/api/public/centers')->assertDontSee('centro-recuperable');
    $this->patchJson('/api/v1/platform/centers/'.$center->id, ['is_active' => true])->assertUnprocessable();

    $provisioner->fail = false;
    $this->postJson('/api/v1/platform/centers/'.$center->id.'/provision')
        ->assertOk()->assertJsonPath('data.provisioning_status', 'READY');
    $this->getJson('/api/public/centers')->assertJsonFragment(['slug' => 'centro-recuperable']);
});

it('rejects clinical users from platform center management', function () {
    $clinical = User::create([
        'name' => 'Centro', 'email' => 'clinical@example.test',
        'password' => 'Password123', 'is_active' => true,
    ]);
    $this->actingAs($clinical)->getJson('/api/v1/platform/centers')->assertForbidden();
});
