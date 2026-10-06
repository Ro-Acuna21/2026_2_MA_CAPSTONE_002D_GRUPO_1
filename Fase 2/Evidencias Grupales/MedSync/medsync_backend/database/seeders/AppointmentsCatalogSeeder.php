<?php

namespace Database\Seeders;

use App\Models\Center\Availability;
use App\Models\Center\Professional;
use App\Models\Center\Service;
use App\Models\Center\Specialty;
use Illuminate\Database\Seeder;

/**
 * Crea el catálogo mínimo (especialidades, prestaciones, disponibilidad y
 * el vínculo profesional-especialidad) necesario para poder probar el
 * flujo de reservas de principio a fin contra los profesionales A, B y C
 * que ya crea MedSyncDemoSeeder.
 *
 * Sin este catálogo, GET /api/v1/services devuelve una lista vacía y no
 * es posible reservar nada todavía.
 */
class AppointmentsCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $specialtiesData = [
            [
                'name' => 'Medicina General',
                'description' => 'Consultas y controles generales.',
                'professional_rut' => '44444444-4', // Ana Profesional A
                'additional_professionals' => [
                    ['rut' => '74444444-4', 'first_name' => 'Diego', 'last_name' => 'Médico General', 'email' => 'diego.general@medsync.test', 'phone' => '+56974444444'],
                    ['rut' => '84444444-4', 'first_name' => 'Fernanda', 'last_name' => 'Médica General', 'email' => 'fernanda.general@medsync.test', 'phone' => '+56984444444'],
                ],
                'services' => [
                    ['name' => 'Consulta general', 'duration_minutes' => 30],
                    ['name' => 'Control de salud', 'duration_minutes' => 20],
                ],
            ],
            [
                'name' => 'Cardiología',
                'description' => 'Evaluación y control cardiovascular.',
                'professional_rut' => '55555555-5', // Bruno Profesional B
                'additional_professionals' => [
                    ['rut' => '75555555-5', 'first_name' => 'Gabriel', 'last_name' => 'Cardiólogo', 'email' => 'gabriel.cardio@medsync.test', 'phone' => '+56975555555'],
                    ['rut' => '85555555-5', 'first_name' => 'Helena', 'last_name' => 'Cardióloga', 'email' => 'helena.cardio@medsync.test', 'phone' => '+56985555555'],
                ],
                'services' => [
                    ['name' => 'Consulta cardiológica', 'duration_minutes' => 30],
                    ['name' => 'Electrocardiograma', 'duration_minutes' => 20],
                ],
            ],
            [
                'name' => 'Pediatría',
                'description' => 'Atención médica infantil.',
                'professional_rut' => '66666666-6', // Carla Profesional C
                'additional_professionals' => [
                    ['rut' => '76666666-6', 'first_name' => 'Ignacio', 'last_name' => 'Pediatra', 'email' => 'ignacio.pediatria@medsync.test', 'phone' => '+56976666666'],
                    ['rut' => '86666666-6', 'first_name' => 'Julieta', 'last_name' => 'Pediatra', 'email' => 'julieta.pediatria@medsync.test', 'phone' => '+56986666666'],
                ],
                'services' => [
                    ['name' => 'Consulta pediátrica', 'duration_minutes' => 30],
                ],
            ],
        ];

        foreach ($specialtiesData as $specialtyData) {
            $specialty = Specialty::updateOrCreate(
                ['name' => $specialtyData['name']],
                [
                    'description' => $specialtyData['description'],
                    'is_active' => true,
                ]
            );

            foreach ($specialtyData['services'] as $serviceData) {
                Service::updateOrCreate(
                    [
                        'specialty_id' => $specialty->id,
                        'name' => $serviceData['name'],
                    ],
                    [
                        'description' => null,
                        'duration_minutes' => $serviceData['duration_minutes'],
                        'is_active' => true,
                    ]
                );
            }

            $professional = Professional::where('rut', $specialtyData['professional_rut'])->first();

            if (! $professional) {
                $this->command->warn(
                    "No se encontró el profesional con RUT {$specialtyData['professional_rut']}; "
                    ."omitiendo vínculo con {$specialtyData['name']}."
                );

                continue;
            }

            $professionals = collect([$professional]);

            foreach ($specialtyData['additional_professionals'] as $professionalData) {
                $professionals->push(Professional::updateOrCreate(
                    ['rut' => $professionalData['rut']],
                    [
                        'user_id' => null,
                        'first_name' => $professionalData['first_name'],
                        'last_name' => $professionalData['last_name'],
                        'email' => $professionalData['email'],
                        'phone' => $professionalData['phone'],
                        'is_active' => true,
                    ]
                ));
            }

            foreach ($professionals as $catalogProfessional) {
                // Vincula la especialidad al profesional (evita duplicar la fila pivote).
                if (! $catalogProfessional->specialties()->whereKey($specialty->id)->exists()) {
                    $catalogProfessional->specialties()->attach($specialty->id);
                }

                // Disponibilidad de lunes a viernes: 09:00-13:00 y 14:00-18:00.
                foreach (range(1, 5) as $weekday) {
                    foreach ([['09:00', '13:00'], ['14:00', '18:00']] as [$start, $end]) {
                        Availability::firstOrCreate(
                            [
                                'professional_id' => $catalogProfessional->id,
                                'weekday' => $weekday,
                                'start_time' => $start,
                            ],
                            [
                                'end_time' => $end,
                                'is_active' => true,
                            ]
                        );
                    }
                }
            }
        }

        $this->command->newLine();
        $this->command->info('Catálogo de especialidades, prestaciones y disponibilidad creado.');
        $this->command->info('Cada especialidad cuenta con tres profesionales activos y horario lunes a viernes, 09:00-13:00 y 14:00-18:00.');
    }
}
