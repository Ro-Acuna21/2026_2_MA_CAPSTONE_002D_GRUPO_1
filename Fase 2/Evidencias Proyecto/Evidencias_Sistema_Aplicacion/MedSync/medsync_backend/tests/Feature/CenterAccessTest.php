<?php

use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Models\User;
use App\Notifications\CenterAccessInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Notification::fake();
    $this->center = MedicalCenter::create([
        'name' => 'Centro Accesos', 'slug' => 'centro-accesos',
        'database_name' => config('database.connections.center.database'),
        'rut' => '76123456-7', 'is_active' => true,
    ]);
    $this->platformUser = User::create([
        'name' => 'Plataforma', 'email' => 'platform@example.test', 'password' => 'Password123',
        'system_role' => 'SUPER_ADMIN', 'is_active' => true,
    ]);
    $this->admin = User::create([
        'name' => 'Admin actual', 'email' => 'admin@example.test', 'password' => 'Password123', 'is_active' => true,
    ]);
    CenterUser::create([
        'medical_center_id' => $this->center->id, 'user_id' => $this->admin->id,
        'role' => 'ADMIN', 'is_active' => true,
    ]);
});

it('invites a new administrator and preserves an existing global password', function () {
    $url = '/api/v1/platform/centers/'.$this->center->id.'/administrators';
    $this->actingAs($this->platformUser)->postJson($url, [
        'name' => 'Nueva Admin', 'email' => 'new-admin@example.test',
    ])->assertCreated()->assertJsonPath('account_created', true)->assertJsonPath('data.invitation_pending', true);
    $new = User::where('email', 'new-admin@example.test')->firstOrFail();
    Notification::assertSentTo($new, CenterAccessInvitationNotification::class);
    $this->postJson($url, ['name' => 'Nueva Admin', 'email' => $new->email])
        ->assertCreated()->assertJsonPath('account_created', false);
    expect(CenterUser::where('medical_center_id', $this->center->id)->where('user_id', $new->id)->count())->toBe(1);

    $existing = User::create([
        'name' => 'Cuenta existente', 'email' => 'existing@example.test',
        'password' => 'OriginalPassword123', 'is_active' => true,
    ]);
    $this->postJson($url, ['name' => 'Cuenta existente', 'email' => $existing->email])
        ->assertCreated()->assertJsonPath('account_created', false);
    expect(Hash::check('OriginalPassword123', $existing->fresh()->password))->toBeTrue();
});

it('keeps an unactivated global account pending when assigned to a second center', function () {
    $otherCenter = MedicalCenter::create([
        'name' => 'Centro Segundo', 'slug' => 'centro-segundo',
        'database_name' => 'centro_segundo', 'rut' => '76123457-5', 'is_active' => true,
    ]);
    $this->actingAs($this->platformUser)->postJson('/api/v1/platform/centers/'.$this->center->id.'/administrators', [
        'name' => 'Nueva Admin', 'email' => 'pending@example.test',
    ])->assertCreated();
    $user = User::where('email', 'pending@example.test')->firstOrFail();
    $password = $user->password;
    $this->postJson('/api/v1/platform/centers/'.$otherCenter->id.'/administrators', [
        'name' => 'Nueva Admin', 'email' => $user->email,
    ])->assertCreated()->assertJsonPath('account_created', false)
        ->assertJsonPath('invitation_sent', true)
        ->assertJsonPath('data.invitation_pending', true);
    expect($user->fresh()->password)->toBe($password);
    Notification::assertSentToTimes($user, CenterAccessInvitationNotification::class, 2);
});

it('lets only the center admin manage reception and blocks other centers', function () {
    $this->actingAs($this->admin)->withHeader('Origin', 'http://localhost:3000')
        ->withSession(['active_medical_center_id' => $this->center->id]);
    $this->postJson('/api/v1/center/receptionists', [
        'name' => 'Recepción Nueva', 'email' => 'reception@example.test',
    ])->assertCreated()->assertJsonPath('data.role', 'RECEPCIONISTA');
    $membership = CenterUser::where('role', 'RECEPCIONISTA')->firstOrFail();
    $this->postJson('/api/v1/center/receptionists/'.$membership->id.'/resend-invitation')->assertOk();
    $this->patchJson('/api/v1/center/receptionists/'.$membership->id, ['is_active' => false])
        ->assertOk()->assertJsonPath('data.is_active', false);
    $otherCenter = MedicalCenter::create([
        'name' => 'Otro Centro', 'slug' => 'otro-centro', 'database_name' => 'otro-centro',
        'rut' => '76123457-5', 'is_active' => true,
    ]);
    $other = CenterUser::create([
        'medical_center_id' => $otherCenter->id, 'user_id' => $membership->user_id,
        'role' => 'RECEPCIONISTA', 'is_active' => true,
    ]);
    $this->patchJson('/api/v1/center/receptionists/'.$other->id, ['is_active' => false])->assertNotFound();
});

it('clears the pending invitation after account activation', function () {
    $this->actingAs($this->platformUser)->postJson('/api/v1/platform/centers/'.$this->center->id.'/administrators', [
        'name' => 'Nueva Admin', 'email' => 'activation@example.test',
    ])->assertCreated();
    $user = User::where('email', 'activation@example.test')->firstOrFail();
    $token = Password::broker()->createToken($user);
    $this->postJson('/api/auth/activate-account', [
        'email' => $user->email, 'token' => $token, 'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertOk();
    expect(Hash::check('NewPassword123!', $user->fresh()->password))->toBeTrue();
    expect(CenterUser::where('user_id', $user->id)->firstOrFail()->invitation_pending)->toBeFalse();

    $this->postJson('/api/auth/activate-account', [
        'email' => $user->email, 'token' => $token, 'password' => 'AnotherPassword123!',
        'password_confirmation' => 'AnotherPassword123!',
    ])->assertUnprocessable();
    expect(Hash::check('NewPassword123!', $user->fresh()->password))->toBeTrue();
});

it('rejects an expired invitation token', function () {
    $this->actingAs($this->platformUser)->postJson('/api/v1/platform/centers/'.$this->center->id.'/administrators', [
        'name' => 'Admin expirado', 'email' => 'expired-invitation@example.test',
    ])->assertCreated();
    $user = User::where('email', 'expired-invitation@example.test')->firstOrFail();
    $token = Password::broker()->createToken($user);
    DB::connection('core')->table('password_reset_tokens')->where('email', $user->email)
        ->update(['created_at' => now()->subMinutes(61)]);

    $this->postJson('/api/auth/activate-account', [
        'email' => $user->email, 'token' => $token, 'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertUnprocessable();
    expect($user->fresh()->email_verified_at)->toBeNull();
    expect(CenterUser::where('user_id', $user->id)->firstOrFail()->invitation_pending)->toBeTrue();
});

it('shows the fixed backend permission catalog only to the center admin', function () {
    $this->actingAs($this->admin)->withHeader('Origin', 'http://localhost:3000')
        ->withSession(['active_medical_center_id' => $this->center->id])
        ->getJson('/api/v1/center/permissions')
        ->assertOk()->assertJsonPath('data.source', 'fixed_backend_catalog')
        ->assertJsonFragment(['patients.manage']);
});

it('keeps platform identity outside every clinical route', function () {
    $this->actingAs($this->platformUser)->withHeader('Origin', 'http://localhost:3000')
        ->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.role', 'SUPER_ADMIN');
    $this->getJson('/api/v1/patients')->assertForbidden();
    $this->getJson('/api/v1/center/permissions')->assertForbidden();
});

it('changes one staff role within its existing membership without leaking permissions to another center', function () {
    $otherCenter = MedicalCenter::create([
        'name' => 'Centro Segundo', 'slug' => 'centro-segundo',
        'database_name' => 'centro_segundo', 'rut' => '76123457-5', 'is_active' => true,
    ]);
    $staff = User::create([
        'name' => 'Equipo Compartido', 'email' => 'shared-staff@example.test',
        'password' => 'Password123', 'is_active' => true,
    ]);
    $membership = CenterUser::create([
        'medical_center_id' => $this->center->id, 'user_id' => $staff->id,
        'role' => 'RECEPCIONISTA', 'is_active' => true,
    ]);
    $patientMembership = CenterUser::create([
        'medical_center_id' => $otherCenter->id, 'user_id' => $staff->id,
        'role' => 'PACIENTE', 'patient_id' => 1, 'is_active' => true,
    ]);
    $this->actingAs($this->platformUser)->patchJson(
        '/api/v1/platform/centers/'.$this->center->id.'/staff/'.$membership->id.'/role',
        ['role' => 'ADMIN'],
    )->assertOk()->assertJsonPath('data.role', 'ADMIN');
    expect(CenterUser::where('medical_center_id', $this->center->id)->where('user_id', $staff->id)->count())->toBe(1);
    expect($patientMembership->fresh()->role)->toBe('PACIENTE');
    $this->patchJson('/api/v1/platform/centers/'.$otherCenter->id.'/staff/'.$patientMembership->id.'/role',
        ['role' => 'ADMIN'])->assertNotFound();
    $registrar = app(PermissionRegistrar::class);
    try {
        $registrar->setPermissionsTeamId($this->center->id);
        $staff->unsetRelation('roles')->unsetRelation('permissions');
        expect($staff->can('professionals.manage'))->toBeTrue();
        expect($staff->can('patients.manage'))->toBeFalse();
        $registrar->setPermissionsTeamId($otherCenter->id);
        $staff->unsetRelation('roles')->unsetRelation('permissions');
        expect($staff->can('patients.view.own'))->toBeTrue();
        expect($staff->can('professionals.manage'))->toBeFalse();
    } finally {
        $registrar->setPermissionsTeamId(null);
        $staff->unsetRelation('roles')->unsetRelation('permissions');
    }
});
