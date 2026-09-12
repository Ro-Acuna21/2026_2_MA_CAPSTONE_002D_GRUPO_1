<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoreMedicalCenterSeeder extends Seeder
{
    public function run(): void
    {
        DB::connection('core')->table('medical_centers')->updateOrInsert(
            ['slug' => 'clinica-horizonte'],
            [
                'name' => 'Clínica Horizonte',
                'database_name' => 'medsync_clinica_horizonte',
                'rut' => '76123456-7',
                'address' => 'Av. Siempre Viva 123',
                'phone' => '+56912345678',
                'email' => 'contacto@clinicahorizonte.cl',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}