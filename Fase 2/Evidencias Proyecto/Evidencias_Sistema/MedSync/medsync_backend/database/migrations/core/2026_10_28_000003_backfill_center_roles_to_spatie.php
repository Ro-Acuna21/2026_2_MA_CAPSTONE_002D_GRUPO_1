<?php

use App\Services\Authorization\CenterRolePermissionSync;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(CenterRolePermissionSync::class)->syncExistingMemberships();
    }

    public function down(): void
    {
        app(CenterRolePermissionSync::class)->removeManagedRolesAndPermissions();
    }
};
