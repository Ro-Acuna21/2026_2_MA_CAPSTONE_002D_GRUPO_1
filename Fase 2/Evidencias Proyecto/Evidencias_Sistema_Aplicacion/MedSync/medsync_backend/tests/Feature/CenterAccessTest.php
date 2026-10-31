<?php

use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Models\User;
use App\Notifications\CenterAccessInvitationNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

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
});
