<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CenterHealthInsuranceSeeder extends Seeder
{
    public function run(): void
    {
        $healthInsurances = [
            ['name' => 'Fonasa', 'type' => 'FONASA'],
            ['name' => 'Isapre', 'type' => 'ISAPRE'],
            ['name' => 'Particular', 'type' => 'PARTICULAR'],
            ['name' => 'Otra', 'type' => 'OTHER'],
        ];

        foreach ($healthInsurances as $healthInsurance) {
            DB::connection('center')->table('health_insurances')->updateOrInsert(
                ['name' => $healthInsurance['name']],
                [
                    'type' => $healthInsurance['type'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}