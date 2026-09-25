<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('center')->create('specialties', function (Blueprint $table) {
            $table->id();

            // Nombre identificador de la especialidad dentro del centro.
            $table->string('name', 120);

            // Información descriptiva opcional para administración o frontend.
            $table->text('description')->nullable();

            // Permite dejar de ofrecer una especialidad sin borrar su historial.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        /*
         * Evita nombres vacíos o compuestos solamente por espacios.
         */
        DB::connection('center')->statement(
            "
            ALTER TABLE specialties
            ADD CONSTRAINT specialties_name_not_blank
            CHECK (BTRIM(name) <> '')
            "
        );

        /*
         * Evita especialidades duplicadas ignorando diferencias
         * de mayúsculas/minúsculas y espacios exteriores.
         *
         * Por ejemplo:
         * Cardiología
         * cardiología
         * ' Cardiología '
         *
         * se consideran el mismo nombre.
         */
        DB::connection('center')->statement(
            "
            CREATE UNIQUE INDEX specialties_name_unique_ci
            ON specialties (LOWER(BTRIM(name)))
            "
        );
    }

    public function down(): void
    {
        Schema::connection('center')->dropIfExists('specialties');
    }
};