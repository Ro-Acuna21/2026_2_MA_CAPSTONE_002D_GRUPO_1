<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('center')->create('medical_results', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->restrictOnDelete();

            $table->foreignId('professional_id')
                ->constrained('professionals')
                ->restrictOnDelete();

            $table->foreignId('result_type_id')
                ->constrained('result_types')
                ->restrictOnDelete();

            $table->foreignId('appointment_id')
                ->nullable()
                ->constrained('appointments')
                ->restrictOnDelete();

            $table->timestamp('performed_at');
            $table->string('status', 20)->default('DRAFT');
            $table->timestamp('published_at')->nullable();

            // Referencias lógicas a usuarios de medsync_core; no admiten FK entre bases.
            $table->bigInteger('created_by');
            $table->bigInteger('published_by')->nullable();

            $table->timestamp('voided_at')->nullable();
            $table->bigInteger('voided_by')->nullable();
            $table->text('void_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['patient_id', 'status', 'performed_at'],
                'medical_results_patient_status_date_index'
            );
            $table->index(
                ['professional_id', 'performed_at'],
                'medical_results_professional_date_index'
            );
        });

        if (DB::connection('center')->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection('center')->statement(
            "ALTER TABLE medical_results
            ADD CONSTRAINT medical_results_valid_status
            CHECK (status IN ('DRAFT', 'PUBLISHED', 'VOIDED'))"
        );
    }

    public function down(): void
    {
        Schema::connection('center')->dropIfExists('medical_results');
    }
};
