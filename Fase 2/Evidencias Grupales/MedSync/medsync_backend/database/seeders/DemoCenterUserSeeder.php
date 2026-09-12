<?php

namespace Database\Seeders;

use App\Models\Center\HealthInsurance;
use App\Models\Center\Patient;
use App\Models\Center\Professional;
use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoCenterUserSeeder extends Seeder
{
    public function run(): void
    {
        $center = MedicalCenter::where('slug', 'clinica-horizonte')->first();

        if (! $center) {
            return;
        }

        $healthInsurance = HealthInsurance::where('name', 'Fonasa')->first();

        if (! $healthInsurance) {
            return;
        }

        $patientUser = User::updateOrCreate(
            ['email' => 'paciente.prueba@test.cl'],
            [
                'name' => 'Paciente Prueba',
                'password' => 'Password123',
                'is_active' => true,
            ]
        );

        $patient = Patient::updateOrCreate(
            ['rut' => '11111111-1'],
            [
                'user_id' => $patientUser->id,
                'health_insurance_id' => $healthInsurance->id,
                'first_name' => 'Paciente',
                'last_name' => 'Prueba',
                'birth_date' => '2000-01-01',
                'email' => 'paciente.prueba@test.cl',
                'phone' => '+56911111111',
                'address' => 'Dirección paciente prueba',
                'medical_insurance' => null,
                'consent_at' => now(),
                'consent_version' => 'v1',
                'is_active' => true,
            ]
        );

        CenterUser::updateOrCreate(
            [
                'medical_center_id' => $center->id,
                'user_id' => $patientUser->id,
            ],
            [
                'role' => 'PACIENTE',
                'patient_id' => $patient->id,
                'professional_id' => null,
                'is_active' => true,
            ]
        );

        $professionalUser = User::updateOrCreate(
            ['email' => 'profesional.prueba@test.cl'],
            [
                'name' => 'Profesional Prueba',
                'password' => 'Password123',
                'is_active' => true,
            ]
        );

        $professional = Professional::updateOrCreate(
            ['rut' => '22222222-2'],
            [
                'user_id' => $professionalUser->id,
                'first_name' => 'Profesional',
                'last_name' => 'Prueba',
                'email' => 'profesional.prueba@test.cl',
                'phone' => '+56922222222',
                'is_active' => true,
            ]
        );

        CenterUser::updateOrCreate(
            [
                'medical_center_id' => $center->id,
                'user_id' => $professionalUser->id,
            ],
            [
                'role' => 'PROFESIONAL',
                'patient_id' => null,
                'professional_id' => $professional->id,
                'is_active' => true,
            ]
        );
    }
}