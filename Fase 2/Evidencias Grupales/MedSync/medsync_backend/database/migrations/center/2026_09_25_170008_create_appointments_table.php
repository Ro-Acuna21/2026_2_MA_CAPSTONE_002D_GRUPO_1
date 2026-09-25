<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('center')->create('appointments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->restrictOnDelete();

            $table->foreignId('professional_id')
                ->constrained('professionals')
                ->restrictOnDelete();

            $table->foreignId('service_id')
                ->constrained('services')
                ->restrictOnDelete();

            $table->date('appointment_date');

            $table->time('start_time');
            $table->time('end_time');

            $table->string('status', 20)
                ->default('PENDIENTE');

            $table->string('source', 20);

            /*
             * Nota exclusivamente administrativa.
             */
            $table->text('note')->nullable();

            /*
             * Indica si la reserva fue autorizada como sobreturno.
             */
            $table->boolean('overbook')->default(false);

            /*
             * Referencia lógica hacia medsync_core.users.
             *
             * No puede utilizarse una FK PostgreSQL tradicional
             * porque users vive en otra base de datos.
             */
            $table->bigInteger('created_by');

            $table->timestamps();

            /*
             * Agenda de un profesional para un día determinado.
             */
            $table->index(
                [
                    'professional_id',
                    'appointment_date',
                    'start_time',
                ],
                'appointments_professional_schedule_index'
            );

            /*
             * Historial y próximas reservas de un paciente.
             */
            $table->index(
                [
                    'patient_id',
                    'appointment_date',
                    'start_time',
                ],
                'appointments_patient_schedule_index'
            );

            /*
             * Búsquedas administrativas por estado y fecha.
             */
            $table->index(
                [
                    'status',
                    'appointment_date',
                ],
                'appointments_status_date_index'
            );

            /*
             * Consultas y reportes relacionados con prestaciones.
             */
            $table->index(
                [
                    'service_id',
                    'appointment_date',
                ],
                'appointments_service_date_index'
            );
        });

        /*
         * La hora de término debe ser posterior
         * a la hora de inicio.
         */
        DB::connection('center')->statement(
            "
            ALTER TABLE appointments
            ADD CONSTRAINT appointments_valid_time
            CHECK (start_time < end_time)
            "
        );

        DB::connection('center')->statement(
            "
            ALTER TABLE appointments
            ADD CONSTRAINT appointments_valid_status
            CHECK (
                status IN (
                    'PENDIENTE',
                    'CONFIRMADA',
                    'ATENDIDA',
                    'CANCELADA',
                    'NO_SHOW'
                )
            )
            "
        );

        DB::connection('center')->statement(
            "
            ALTER TABLE appointments
            ADD CONSTRAINT appointments_valid_source
            CHECK (
                source IN (
                    'WEB',
                    'RECEPCION',
                    'DEMO'
                )
            )
            "
        );
    }

    public function down(): void
    {
        Schema::connection('center')->dropIfExists('appointments');
    }
};