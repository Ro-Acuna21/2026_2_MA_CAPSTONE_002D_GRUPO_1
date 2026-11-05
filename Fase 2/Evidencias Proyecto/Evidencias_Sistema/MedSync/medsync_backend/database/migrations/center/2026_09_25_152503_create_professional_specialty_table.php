<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('center')->create(
            'professional_specialty',
            function (Blueprint $table) {
                /*
                 * Profesional perteneciente a la relación.
                 */
                $table->foreignId('professional_id');

                /*
                 * Especialidad perteneciente a la relación.
                 */
                $table->foreignId('specialty_id');

                $table->timestamps();

                /*
                 * La combinación constituye la identidad de la relación.
                 *
                 * Impide que el mismo profesional sea asociado dos veces
                 * a la misma especialidad.
                 */
                $table->primary(
                    [
                        'professional_id',
                        'specialty_id',
                    ],
                    'professional_specialty_pk'
                );

                /*
                 * Ambas referencias existen dentro de la misma BD center,
                 * por lo que podemos utilizar claves foráneas reales.
                 */
                $table->foreign(
                    'professional_id',
                    'professional_specialty_professional_fk'
                )
                    ->references('id')
                    ->on('professionals')
                    ->restrictOnDelete();

                $table->foreign(
                    'specialty_id',
                    'professional_specialty_specialty_fk'
                )
                    ->references('id')
                    ->on('specialties')
                    ->restrictOnDelete();

                /*
                 * La PK ya crea un índice comenzando por professional_id.
                 *
                 * Este segundo índice optimiza la consulta inversa:
                 * "obtener todos los profesionales de una especialidad".
                 */
                $table->index(
                    'specialty_id',
                    'professional_specialty_specialty_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::connection('center')
            ->dropIfExists('professional_specialty');
    }
};