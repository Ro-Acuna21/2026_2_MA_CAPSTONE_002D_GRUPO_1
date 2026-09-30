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
                'services' => [
                    ['name' => 'Consulta general', 'duration_minutes' => 30],
                    ['name' => 'Control de salud', 'duration_minutes' => 20],
                ],
            ],
            [
                'name' => 'Cardiología',
                'description' => 'Evaluación y control cardiovascular.',
                'professional_rut' => '55555555-5', // Bruno Profesional B
                'services' => [
                    ['name' => 'Consulta cardiológica', 'duration_minutes' => 30],
                    ['name' => 'Electrocardiograma', 'duration_minutes' => 20],
                ],
            ],
            [
                'name' => 'Pediatría',
                'description' => 'Atención médica infantil.',
                'professional_rut' => '66666666-6', // Carla Profesional C
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

            // Vincula la especialidad al profesional (evita duplicar la fila pivote).
            if (! $professional->specialties()->whereKey($specialty->id)->exists()) {
                $professional->specialties()->attach($specialty->id);
            }

            // Disponibilidad de lunes a viernes: 09:00-13:00 y 14:00-18:00.
            foreach (range(1, 5) as $weekday) {
                foreach ([['09:00', '13:00'], ['14:00', '18:00']] as [$start, $end]) {
                    Availability::firstOrCreate(
                        [
                            'professional_id' => $professional->id,
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

        $this->command->newLine();
        $this->command->info('Catálogo de especialidades, prestaciones y disponibilidad creado.');
        $this->command->info('Profesionales A, B y C quedaron con horario lunes a viernes, 09:00-13:00 y 14:00-18:00.');
    }
}
