<?php

namespace Database\Seeders;

use App\Models\Center\HealthInsurance;
use App\Models\Center\Patient;
use App\Models\Center\PatientAddress;
use App\Models\Center\Professional;
use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Models\User;
use Illuminate\Database\Seeder;

class MedSyncDemoSeeder extends Seeder
{
    public function run(): void
    {
        /*
        Obtener Clínica Horizonte
        */

        $center = MedicalCenter::where('slug', 'clinica-horizonte')->first();

        if (! $center) {
            $this->command->error('No se encontró Clínica Horizonte.');
            return;
        }

        /*
        Obtener Fonasa
        */

        $healthInsurance = HealthInsurance::where('name', 'Fonasa')->first();

        if (! $healthInsurance) {
            $this->command->error('No se encontró Fonasa.');
            return;
        }

        /*
        ADMINISTRADOR DE CLÍNICA HORIZONTE
        */

        $adminUser = User::updateOrCreate(
            ['email' => 'admin@clinicahorizonte.cl'],
            [
                'name' => 'Administrador Clínica Horizonte',
                'password' => 'Password123',
                'system_role' => null,
                'is_active' => true,
            ]
        );

        CenterUser::updateOrCreate(
            [
                'medical_center_id' => $center->id,
                'user_id' => $adminUser->id,
            ],
            [
                'role' => 'ADMIN',
                'patient_id' => null,
                'professional_id' => null,
                'is_active' => true,
            ]
        );

        /*
        PACIENTES A, B Y C
        */

        $patients = [
            [
                'name' => 'Paciente Prueba A',
                'first_name' => 'Paciente',
                'last_name' => 'Prueba A',
                'rut' => '11111111-1',
                'birth_date' => '2000-01-10',
                'email' => 'paciente.a@medsync.test',
                'phone' => '+56911111111',
                'address' => 'Av. Paciente 111',
            ],
            [
                'name' => 'Paciente Prueba B',
                'first_name' => 'Paciente',
                'last_name' => 'Prueba B',
                'rut' => '22222222-2',
                'birth_date' => '1998-05-15',
                'email' => 'paciente.b@medsync.test',
                'phone' => '+56922222222',
                'address' => 'Av. Paciente 222',
            ],
            [
                'name' => 'Paciente Prueba C',
                'first_name' => 'Paciente',
                'last_name' => 'Prueba C',
                'rut' => '33333333-3',
                'birth_date' => '1995-09-20',
                'email' => 'paciente.c@medsync.test',
                'phone' => '+56933333333',
                'address' => 'Av. Paciente 333',
            ],
        ];

        foreach ($patients as $patientData) {

            /*
             * Crear cuenta global del paciente
             */
            $patientUser = User::updateOrCreate(
                ['email' => $patientData['email']],
                [
                    'name' => $patientData['name'],
                    'password' => 'Password123',
                    'system_role' => null,
                    'is_active' => true,
                ]
            );

            /*
             * Crear ficha del paciente en Clínica Horizonte
             */
            $patient = Patient::updateOrCreate(
                ['rut' => $patientData['rut']],
                [
                    'user_id' => $patientUser->id,
                    'health_insurance_id' => $healthInsurance->id,
                    'first_name' => $patientData['first_name'],
                    'last_name' => $patientData['last_name'],
                    'birth_date' => $patientData['birth_date'],
                    'email' => $patientData['email'],
                    'phone' => $patientData['phone'],
                    'medical_insurance' => null,
                    'consent_at' => now(),
                    'consent_version' => 'v1',
                    'is_active' => true,
                ]
            );

            /*
             * Dirección principal
             */
            PatientAddress::updateOrCreate(
                [
                    'patient_id' => $patient->id,
                    'is_primary' => true,
                ],
                [
                    'address_line' => $patientData['address'],
                    'commune' => 'Maipú',
                    'region' => 'Región Metropolitana',
                    'postal_code' => null,
                    'reference' => null,
                ]
            );

            /*
             * Relacionar usuario con Clínica Horizonte
             */
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
        }

        /*
        | PROFESIONALES A, B Y C
        |
        | Se crean como registros administrativos.
        | Por ahora no necesitan una cuenta de usuario.
        */

        $professionals = [
            [
                'first_name' => 'Ana',
                'last_name' => 'Profesional A',
                'rut' => '44444444-4',
                'email' => 'profesional.a@medsync.test',
                'phone' => '+56944444444',
            ],
            [
                'first_name' => 'Bruno',
                'last_name' => 'Profesional B',
                'rut' => '55555555-5',
                'email' => 'profesional.b@medsync.test',
                'phone' => '+56955555555',
            ],
            [
                'first_name' => 'Carla',
                'last_name' => 'Profesional C',
                'rut' => '66666666-6',
                'email' => 'profesional.c@medsync.test',
                'phone' => '+56966666666',
            ],
        ];

        foreach ($professionals as $professionalData) {
            Professional::updateOrCreate(
                ['rut' => $professionalData['rut']],
                [
                    'user_id' => null,
                    'first_name' => $professionalData['first_name'],
                    'last_name' => $professionalData['last_name'],
                    'email' => $professionalData['email'],
                    'phone' => $professionalData['phone'],
                    'is_active' => true,
                ]
            );
        }

        /*
        | Mensaje final
        */

        $this->command->newLine();

        $this->command->info(
            'Datos de demostración de MedSync creados correctamente.'
        );

        $this->command->newLine();

        $this->command->info('SUPER ADMIN');
        $this->command->info('Correo: admin@medsync.cl');
        $this->command->info('Contraseña: Password123');

        $this->command->newLine();

        $this->command->info('ADMIN CLÍNICA HORIZONTE');
        $this->command->info('Correo: admin@clinicahorizonte.cl');
        $this->command->info('Contraseña: Password123');

        $this->command->newLine();

        $this->command->info(
            'Pacientes A, B y C creados con contraseña Password123'
        );

        $this->command->info(
            'Profesionales A, B y C creados como registros administrativos.'
        );
    }
}