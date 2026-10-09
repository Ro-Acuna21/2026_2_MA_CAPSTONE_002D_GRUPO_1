<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('center')->hasTable('appointment_history')) {
            return;
        }

        Schema::connection('center')->table('appointment_history', function ($table) {
            $table->index(
                ['old_professional_id', 'new_professional_id'],
                'appointment_history_reassignment_professionals_index'
            );
        });

        if (DB::connection('center')->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection('center')->statement('ALTER TABLE appointment_history DROP CONSTRAINT IF EXISTS appointment_history_valid_event');
        DB::connection('center')->statement(
            "ALTER TABLE appointment_history ADD CONSTRAINT appointment_history_valid_event CHECK (event_type IN ('CREACION', 'CAMBIO_ESTADO', 'REPROGRAMACION', 'REASIGNACION', 'MODIFICACION', 'CANCELACION'))"
        );
    }

    public function down(): void
    {
        if (! Schema::connection('center')->hasTable('appointment_history')) {
            return;
        }

        Schema::connection('center')->table('appointment_history', function ($table) {
            $table->dropIndex('appointment_history_reassignment_professionals_index');
        });

        if (DB::connection('center')->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection('center')->statement('ALTER TABLE appointment_history DROP CONSTRAINT IF EXISTS appointment_history_valid_event');
        DB::connection('center')->statement(
            "ALTER TABLE appointment_history ADD CONSTRAINT appointment_history_valid_event CHECK (event_type IN ('CREACION', 'CAMBIO_ESTADO', 'REPROGRAMACION', 'MODIFICACION', 'CANCELACION'))"
        );
    }
};
