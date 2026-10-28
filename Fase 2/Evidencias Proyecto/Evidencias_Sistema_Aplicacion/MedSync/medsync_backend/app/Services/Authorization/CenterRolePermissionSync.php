<?php

namespace App\Services\Authorization;

use App\Models\Core\CenterUser;
use App\Models\Core\Permission;
use App\Models\Core\Role;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

class CenterRolePermissionSync
{
    public const ROLE_PERMISSIONS = [
        'ADMIN' => [
            'professionals.manage',
            'professionals.access.manage',
            'services.manage',
            'availability.manage',
            'center.specialties.manage',
        ],
        'RECEPCIONISTA' => [
            'patients.manage',
            'appointments.view.all',
            'appointments.create.for_patient',
            'appointments.reschedule.for_patient',
            'appointments.status.manage',
        ],
        'PROFESIONAL' => [
            'appointments.view.own',
            'appointments.status.manage.own',
        ],
        'PACIENTE' => [
            'patients.view.own',
            'patients.update.own',
            'appointments.view.own',
            'appointments.create.own',
            'appointments.reschedule.own',
            'appointments.cancel.own',
        ],
    ];

    public function syncExistingMemberships(): void
    {
        $this->ensurePermissions();

        CenterUser::query()->orderBy('id')->each(function (CenterUser $membership): void {
            $this->syncMembership($membership);
        });
    }

    public function syncMembership(CenterUser $membership): void
    {
        $role = $membership->role;
        if (! isset(self::ROLE_PERMISSIONS[$role])) {
            throw new \InvalidArgumentException("Rol de centro no reconocido: {$role}.");
        }

        $this->ensurePermissions();
        $permissions = self::ROLE_PERMISSIONS[$role];
        $centerRole = Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
            'medical_center_id' => $membership->medical_center_id,
        ]);
        $centerRole->syncPermissions($permissions);

        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();
        $user = User::query()->findOrFail($membership->user_id);

        try {
            $registrar->setPermissionsTeamId($membership->medical_center_id);
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $user->syncRoles($centerRole);
        } finally {
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }

    public function removeMembership(CenterUser $membership): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();
        $user = User::query()->find($membership->user_id);

        if (! $user) {
            return;
        }

        try {
            $registrar->setPermissionsTeamId($membership->medical_center_id);
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $user->removeRole($membership->role);
        } finally {
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }

    public function removeManagedRolesAndPermissions(): void
    {
        Role::query()->whereIn('name', array_keys(self::ROLE_PERMISSIONS))->delete();
        Permission::query()->whereIn('name', $this->permissionNames())->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function ensurePermissions(): void
    {
        foreach ($this->permissionNames() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    /** @return list<string> */
    private function permissionNames(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::ROLE_PERMISSIONS))));
    }
}
