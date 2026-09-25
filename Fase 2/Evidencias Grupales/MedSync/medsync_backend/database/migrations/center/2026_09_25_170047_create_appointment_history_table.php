<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('center')->create(
            'appointment_history',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('appointment_id')
                    ->constrained('appointments')
                    ->restrictOnDelete();

                /*
                 * Usuario de medsync_core que realizó la acción.
                 */
                $table->bigInteger('actor_user_id')->nullable();

                $table->string('event_type', 30);

                $table->string('previous_status', 20)->nullable();
                $table->string('new_status', 20)->nullable();

                $table->date('old_date')->nullable();
                $table->time('old_start_time')->nullable();
                $table->time('old_end_time')->nullable();

                $table->date('new_date')->nullable();
                $table->time('new_start_time')->nullable();
                $table->time('new_end_time')->nullable();

                /*
                 * Permiten auditar cambios de profesional.
                 */
                $table->foreignId('old_professional_id')
                    ->nullable()
                    ->constrained('professionals')
                    ->restrictOnDelete();

                $table->foreignId('new_professional_id')
                    ->nullable()
                    ->constrained('professionals')
                    ->restrictOnDelete();

                /*
                 * Permiten auditar cambios de prestación.
                 */
                $table->foreignId('old_service_id')
                    ->nullable()
                    ->constrained('services')
                    ->restrictOnDelete();

                $table->foreignId('new_service_id')
                    ->nullable()
                    ->constrained('services')
                    ->restrictOnDelete();

                /*
                 * Motivo administrativo del cambio.
                 */
                $table->text('reason')->nullable();

                /*
                 * No existe updated_at:
                 * los eventos históricos no se modifican.
                 */
                $table->timestamp('created_at')
                    ->useCurrent();

                $table->index(
                    [
                        'appointment_id',
                        'created_at',
                    ],
                    'appointment_history_appointment_date_index'
                );
            }
        );

        DB::connection('center')->statement(
            "
            ALTER TABLE appointment_history
            ADD CONSTRAINT appointment_history_valid_event
            CHECK (
                event_type IN (
                    'CREACION',
                    'CAMBIO_ESTADO',
                    'REPROGRAMACION',
                    'MODIFICACION',
                    'CANCELACION'
                )
            )
            "
        );

        DB::connection('center')->statement(
            "
            ALTER TABLE appointment_history
            ADD CONSTRAINT appointment_history_previous_status
            CHECK (
                previous_status IS NULL
                OR previous_status IN (
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
            ALTER TABLE appointment_history
            ADD CONSTRAINT appointment_history_new_status
            CHECK (
                new_status IS NULL
                OR new_status IN (
                    'PENDIENTE',
                    'CONFIRMADA',
                    'ATENDIDA',
                    'CANCELADA',
                    'NO_SHOW'
                )
            )
            "
        );
    }

    public function down(): void
    {
        Schema::connection('center')
            ->dropIfExists('appointment_history');
    }
};