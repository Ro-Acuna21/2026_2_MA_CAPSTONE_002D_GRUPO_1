<?php

namespace Database\Seeders;

use App\Models\Core\MedicalCenter;
use App\Models\Core\MedicalCenterAddress;
use Illuminate\Database\Seeder;

class CoreMedicalCenterSeeder extends Seeder
{
    public function run(): void
    {
        $center = MedicalCenter::updateOrCreate(
            ['slug' => 'clinica-horizonte'],
            [
                'name' => 'Clínica Horizonte',
                'database_name' => 'medsync_clinica_horizonte',
                'rut' => '76123456-7',
                'phone' => '+56912345678',
                'email' => 'contacto@clinicahorizonte.cl',
                'is_active' => true,
            ]
        );

        MedicalCenterAddress::updateOrCreate(
            [
                'medical_center_id' => $center->id,
                'is_primary' => true,
            ],
            [
                'address_line' => 'Av. Siempre Viva 123',
                'commune' => 'Maipú',
                'region' => 'Región Metropolitana',
                'postal_code' => null,
                'reference' => null,
            ]
        );
    }
}