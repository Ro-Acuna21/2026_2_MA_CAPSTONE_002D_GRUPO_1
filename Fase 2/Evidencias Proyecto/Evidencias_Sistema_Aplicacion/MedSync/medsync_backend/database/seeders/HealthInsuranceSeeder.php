<?php

namespace Database\Seeders;

use App\Models\HealthInsurance;
use Illuminate\Database\Seeder;

class HealthInsuranceSeeder extends Seeder
{
    /**
     * Catálogo fijo de previsiones que ofrece el formulario de registro del frontend
     * (src/domain/validation.ts -> healthInsurances). El nombre debe coincidir
     * exactamente con la etiqueta que envía el frontend.
     */
    public function run(): void
    {
        $items = [
            ['name' => 'Fonasa', 'type' => 'FONASA'],
            ['name' => 'Isapre', 'type' => 'ISAPRE'],
            ['name' => 'Particular', 'type' => 'PARTICULAR'],
            ['name' => 'Otra', 'type' => 'OTHER'],
        ];

        foreach ($items as $item) {
            HealthInsurance::firstOrCreate(
                ['name' => $item['name']],
                ['type' => $item['type'], 'is_active' => true]
            );
        }
    }
}
