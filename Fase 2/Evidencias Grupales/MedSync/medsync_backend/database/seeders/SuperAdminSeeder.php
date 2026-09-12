<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@medsync.cl'],
            [
                'name' => 'Admin MedSync',
                'password' => 'Password123',
                'system_role' => 'SUPER_ADMIN',
                'is_active' => true,
            ]
        );
    }
}