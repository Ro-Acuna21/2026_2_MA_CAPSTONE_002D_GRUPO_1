<?php

namespace Database\Seeders;

use App\Models\MedicalCenter;
use Illuminate\Database\Seeder;

class MedicalCenterSeeder extends Seeder
{
    /**
     * Iteración 1: un solo centro médico fijo ("Clínica Horizonte").
     * Usa firstOrCreate por el slug para que el seeder sea idempotente.
     */
    public function run(): void
    {
        MedicalCenter::firstOrCreate(
            ['slug' => 'clinica-horizonte'],
            [
                'name' => 'Clínica Horizonte',
                // TODO: reemplazar por el RUT real del centro cuando se defina.
                'rut' => '76543210-3',
                'address' => 'Av. Providencia 1234, Providencia, Santiago',
                'phone' => '+56221234567',
                'email' => 'contacto@clinicahorizonte.cl',
                'is_active' => true,
            ]
        );
    }
}
