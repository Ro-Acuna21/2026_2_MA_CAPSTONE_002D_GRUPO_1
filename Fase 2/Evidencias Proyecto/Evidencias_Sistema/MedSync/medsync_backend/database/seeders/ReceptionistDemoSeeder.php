<?php

namespace Database\Seeders;

use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReceptionistDemoSeeder extends Seeder
{
    public function run(): void
    {
        $center = MedicalCenter::query()
            ->where('slug', 'clinica-horizonte')
            ->first();

        if (! $center) {
            $this->command->error('No se encontró Clínica Horizonte para crear la cuenta de recepción.');

            return;
        }

        $user = User::updateOrCreate(
            ['email' => 'recepcion@clinicahorizonte.cl'],
            [
                'name' => 'Recepción Clínica Horizonte',
                'password' => 'Password123',
                'system_role' => null,
                'is_active' => true,
            ]
        );

        CenterUser::updateOrCreate(
            [
                'medical_center_id' => $center->id,
                'user_id' => $user->id,
            ],
            [
                'role' => 'RECEPCIONISTA',
                'patient_id' => null,
                'professional_id' => null,
                'is_active' => true,
            ]
        );

        $this->command->info('Cuenta demo de recepción de Clínica Horizonte disponible: recepcion@clinicahorizonte.cl');
    }
}
