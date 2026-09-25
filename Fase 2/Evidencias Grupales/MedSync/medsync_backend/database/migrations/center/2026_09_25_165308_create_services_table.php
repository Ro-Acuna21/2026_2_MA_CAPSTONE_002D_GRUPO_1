<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('center')->create('services', function (Blueprint $table) {
            $table->id();

            /*
             * Especialidad a la que pertenece la prestación.
             */
            $table->foreignId('specialty_id')
                ->constrained('specialties')
                ->restrictOnDelete();

            /*
             * Nombre administrativo de la prestación.
             */
            $table->string('name', 120);

            /*
             * Descripción opcional de la prestación.
             */
            $table->text('description')->nullable();

            /*
             * Duración estándar expresada en minutos.
             */
            $table->smallInteger('duration_minutes');

            /*
             * Una prestación se desactiva en vez de eliminarse
             * cuando debe conservarse información histórica.
             */
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            /*
             * Optimiza la obtención de prestaciones activas
             * pertenecientes a una especialidad.
             */
            $table->index(
                ['specialty_id', 'is_active'],
                'services_specialty_active_index'
            );
        });

        /*
         * Impide nombres vacíos.
         */
        DB::connection('center')->statement(
            "
            ALTER TABLE services
            ADD CONSTRAINT services_name_not_blank
            CHECK (BTRIM(name) <> '')
            "
        );

        /*
         * Una prestación debe tener una duración positiva.
         */
        DB::connection('center')->statement(
            "
            ALTER TABLE services
            ADD CONSTRAINT services_duration_positive
            CHECK (duration_minutes > 0)
            "
        );

        /*
         * Una especialidad no puede contener dos prestaciones
         * con el mismo nombre ignorando mayúsculas y espacios.
         */
        DB::connection('center')->statement(
            "
            CREATE UNIQUE INDEX services_specialty_name_unique_ci
            ON services (
                specialty_id,
                LOWER(BTRIM(name))
            )
            "
        );
    }

    public function down(): void
    {
        Schema::connection('center')->dropIfExists('services');
    }
};