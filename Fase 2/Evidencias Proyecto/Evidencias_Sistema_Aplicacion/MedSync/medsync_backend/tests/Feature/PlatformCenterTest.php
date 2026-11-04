<?php

use App\Models\Core\MedicalCenter;
use App\Models\User;
use App\Services\Tenants\CenterProvisioner;
use App\Services\Tenants\CenterDatabaseName;

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
        'address_line' => 'Calle Ficticia 123', 'region_code' => '13', 'commune_code' => '13101',
        'database_name' => 'base_inyectada',
        'plan' => 'STARTER', 'subscription_status' => 'TRIAL', 'is_active' => true,
    ])->assertCreated()->assertJsonPath('data.provisioning_status', 'READY');

    $center = MedicalCenter::where('slug', 'centro-nuevo')->firstOrFail();
    expect($center->database_name)->toBe('medsync_centro_nuevo');
    expect($center->primaryAddress->commune)->toBe('Santiago');
    $this->getJson('/api/public/centers')->assertOk()->assertJsonFragment(['slug' => 'centro-nuevo'])
        ->assertJsonPath('data.0.address', 'Calle Ficticia 123')
        ->assertJsonPath('data.0.description', 'Atención de prueba para la comunidad.');
    $this->postJson('/api/v1/platform/centers', [
        'name' => 'Centro Repetido', 'slug' => 'centro-nuevo', 'rut' => '12.345.678-5',
        'plan' => 'STARTER', 'subscription_status' => 'TRIAL', 'is_active' => true,
    ])->assertUnprocessable();
});

it('uses safe readable names and leaves an existing center database unchanged when its slug changes', function () {
    expect(CenterDatabaseName::fromSlug('clinica-maipu'))->toBe('medsync_clinica_maipu');
    $long = 'centro-'.str_repeat('a', 80);
    $name = CenterDatabaseName::fromSlug($long);
    expect(strlen($name))->toBe(63);
    expect($name)->not->toBe(CenterDatabaseName::fromSlug($long.'b'));

    $center = MedicalCenter::create([
        'name' => 'Centro Histórico', 'slug' => 'centro-historico', 'rut' => '76543210-3',
        'database_name' => 'medsync_c_01old', 'is_active' => true,
    ]);
    $this->actingAs($this->platformUser)->patchJson('/api/v1/platform/centers/'.$center->id, [
        'slug' => 'centro-renombrado',
    ])->assertOk();
    expect($center->fresh()->database_name)->toBe('medsync_c_01old');
});

it('refuses a generated database name already assigned to another center', function () {
    MedicalCenter::create([
        'name' => 'Centro Previo', 'slug' => 'otro-slug', 'rut' => '76543210-3',
        'database_name' => 'medsync_centro_nuevo', 'is_active' => true,
    ]);
    $this->actingAs($this->platformUser)->postJson('/api/v1/platform/centers', [
        'name' => 'Centro Nuevo', 'slug' => 'centro-nuevo', 'rut' => '12.345.678-5',
        'database_name' => 'base_inyectada', 'is_active' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('slug');
    expect(MedicalCenter::count())->toBe(1);
});

it('rejects a commune outside its region and preserves legacy addresses on unrelated edits', function () {
    $center = MedicalCenter::create([
        'name' => 'Centro Legado', 'slug' => 'centro-legado', 'rut' => '76543210-3',
        'database_name' => 'medsync_legacy', 'is_active' => true,
    ]);
    $center->primaryAddress()->create([
        'address_line' => 'Calle 1', 'region' => 'Nombre legado', 'commune' => 'Comuna legada', 'is_primary' => true,
    ]);
    $this->actingAs($this->platformUser)->patchJson('/api/v1/platform/centers/'.$center->id, [
        'name' => 'Centro Legado Editado',
    ])->assertOk();
    expect($center->fresh()->primaryAddress->region)->toBe('Nombre legado');
    $this->patchJson('/api/v1/platform/centers/'.$center->id, [
        'address_line' => 'Calle 2', 'region_code' => '13', 'commune_code' => '05101',
    ])->assertUnprocessable()->assertJsonValidationErrors('commune_code');
    expect($center->fresh()->primaryAddress->address_line)->toBe('Calle 1');
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
