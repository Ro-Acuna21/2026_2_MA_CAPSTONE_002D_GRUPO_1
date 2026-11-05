<?php

use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Models\User;
use App\Services\Authorization\CenterRolePermissionSync;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

it('synchronizes center roles and keeps the same user permissions isolated by center', function () {
    $centerA = MedicalCenter::create([
        'name' => 'Centro Permisos A', 'slug' => 'permisos-a', 'database_name' => 'permisos_a',
        'rut' => '76543210-3', 'is_active' => true,
    ]);
    $centerB = MedicalCenter::create([
        'name' => 'Centro Permisos B', 'slug' => 'permisos-b', 'database_name' => 'permisos_b',
        'rut' => '76543211-1', 'is_active' => true,
    ]);
    $user = User::create([
        'name' => 'Usuario Multi Centro', 'email' => 'multi@example.com',
        'password' => 'Password123', 'is_active' => true,
    ]);

    CenterUser::create([
        'medical_center_id' => $centerA->id, 'user_id' => $user->id,
        'role' => 'RECEPCIONISTA', 'is_active' => true,
    ]);
    CenterUser::create([
        'medical_center_id' => $centerB->id, 'user_id' => $user->id,
        'role' => 'PACIENTE', 'is_active' => true,
    ]);

    $registrar = app(PermissionRegistrar::class);
    try {
        $registrar->setPermissionsTeamId($centerA->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        expect($user->can('patients.manage'))->toBeTrue();
        expect($user->can('appointments.create.for_patient'))->toBeTrue();
        expect($user->can('appointments.create.own'))->toBeFalse();

        $registrar->setPermissionsTeamId($centerB->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        expect($user->can('patients.manage'))->toBeFalse();
        expect($user->can('appointments.create.own'))->toBeTrue();
        expect($user->can('appointments.create.for_patient'))->toBeFalse();
    } finally {
        $registrar->setPermissionsTeamId(null);
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }
});

it('backfills Spatie roles for center memberships that predate the integration', function () {
    $center = MedicalCenter::create([
        'name' => 'Centro previo', 'slug' => 'permisos-previo', 'database_name' => 'permisos_previo',
        'rut' => '76543212-8', 'is_active' => true,
    ]);
    $user = User::create([
        'name' => 'Recepción anterior', 'email' => 'recepcion-previa@example.com',
        'password' => 'Password123', 'is_active' => true,
    ]);

    DB::connection('core')->table('center_users')->insert([
        'medical_center_id' => $center->id,
        'user_id' => $user->id,
        'role' => 'RECEPCIONISTA',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CenterRolePermissionSync::class)->syncExistingMemberships();

    $registrar = app(PermissionRegistrar::class);
    try {
        $registrar->setPermissionsTeamId($center->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        expect($user->can('patients.manage'))->toBeTrue()
            ->and($user->can('appointments.create.for_patient'))->toBeTrue();
    } finally {
        $registrar->setPermissionsTeamId(null);
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }
});
