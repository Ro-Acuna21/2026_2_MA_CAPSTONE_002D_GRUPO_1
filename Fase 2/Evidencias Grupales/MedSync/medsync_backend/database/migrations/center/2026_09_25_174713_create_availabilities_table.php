<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('center')->create('availabilities', function (Blueprint $table) {
            $table->id();

            /*
             * Profesional propietario del bloque de disponibilidad.
             *
             * Si un profesional se elimina físicamente, sus bloques
             * de disponibilidad dejan de tener utilidad.
             */
            $table->foreignId('professional_id')
                ->constrained('professionals')
                ->cascadeOnDelete();

            /*
             * Día de la semana según numeración ISO:
             * 1 = lunes, 7 = domingo.
             */
            $table->smallInteger('weekday');

            /*
             * Horario en que el profesional puede recibir reservas.
             */
            $table->time('start_time');
            $table->time('end_time');

            /*
             * Permite deshabilitar temporalmente un bloque
             * sin eliminar su configuración.
             */
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            /*
             * Optimiza la consulta de disponibilidad de un
             * profesional para un día específico.
             */
            $table->index(
                [
                    'professional_id',
                    'weekday',
                    'is_active',
                    'start_time',
                ],
                'availabilities_professional_weekday_index'
            );
        });

        /*
         * Los días válidos van de lunes (1) a domingo (7).
         */
        DB::connection('center')->statement(
            "
            ALTER TABLE availabilities
            ADD CONSTRAINT availabilities_valid_weekday
            CHECK (weekday BETWEEN 1 AND 7)
            "
        );

        /*
         * El término del bloque siempre debe ser posterior
         * a su hora de inicio.
         */
        DB::connection('center')->statement(
            "
            ALTER TABLE availabilities
            ADD CONSTRAINT availabilities_valid_time
            CHECK (start_time < end_time)
            "
        );
    }

    public function down(): void
    {
        Schema::connection('center')
            ->dropIfExists('availabilities');
    }
};