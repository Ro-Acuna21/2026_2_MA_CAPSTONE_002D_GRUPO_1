<?php

namespace Database\Seeders;

use App\Models\Center\Availability;
use App\Models\Center\Appointment;
use App\Models\Center\Patient;
use App\Models\Center\Professional;
use App\Models\Center\Service;
use App\Models\Center\Specialty;
use App\Models\User;
use Illuminate\Support\Carbon;
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

        /*
         * Citas reales y reproducibles para la demostración de recepción.
         * No se usan identificadores del frontend ni se duplica una cita si
         * el equipo ejecuta el seeder más de una vez.
         */
        $receptionistId = User::where('email', 'recepcion@clinicahorizonte.cl')->value('id');
        $patients = Patient::where('is_active', true)->orderBy('id')->take(3)->get();
        $nextMonday = Carbon::now('America/Santiago')->next(Carbon::MONDAY)->toDateString();

        if ($receptionistId && $patients->count() === 3) {
            foreach ([
                ['specialty' => 'Medicina General', 'patient' => 0, 'time' => '09:00'],
                ['specialty' => 'Cardiología', 'patient' => 1, 'time' => '10:00'],
                ['specialty' => 'Pediatría', 'patient' => 2, 'time' => '11:00'],
            ] as $demoAppointment) {
                $specialty = Specialty::where('name', $demoAppointment['specialty'])->first();
                $service = $specialty?->services()->where('is_active', true)->orderBy('id')->first();
                $professional = $specialty?->professionals()->where('is_active', true)->orderBy('id')->first();

                if (! $service || ! $professional) {
                    continue;
                }

                $startTime = $demoAppointment['time'];
                $endTime = Carbon::createFromFormat('H:i', $startTime)
                    ->addMinutes($service->duration_minutes)
                    ->format('H:i:s');

                Appointment::firstOrCreate(
                    [
                        'patient_id' => $patients[$demoAppointment['patient']]->id,
                        'professional_id' => $professional->id,
                        'service_id' => $service->id,
                        'appointment_date' => $nextMonday,
                        'start_time' => $startTime,
                    ],
                    [
                        'end_time' => $endTime,
                        'status' => 'CONFIRMADA',
                        'source' => 'RECEPCION',
                        'overbook' => false,
                        'created_by' => $receptionistId,
                    ]
                );
            }
        }

        $this->command->newLine();
        $this->command->info('Catálogo de especialidades, prestaciones y disponibilidad creado.');
        $this->command->info('Cada especialidad cuenta con tres profesionales activos y horario lunes a viernes, 09:00-13:00 y 14:00-18:00.');
        $this->command->info('También se aseguran tres citas reales de demostración para la agenda de recepción.');
    }
}
