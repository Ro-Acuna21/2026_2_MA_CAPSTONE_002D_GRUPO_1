<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('center')->create('care_indications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('professional_id')->constrained('professionals')->restrictOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->unsignedBigInteger('amount_clp');
            $table->string('status', 20)->default('INDICATED');
            $table->unsignedBigInteger('indicated_by'); // Core user ID.
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 300)->nullable();
            $table->timestamps();
            $table->unique(['appointment_id', 'service_id']);
            $table->index(['patient_id', 'status']);
        });

        Schema::connection('center')->create('patient_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->unsignedBigInteger('amount_clp');
            $table->string('method', 20);
            $table->string('reference', 120)->nullable();
            $table->unsignedBigInteger('recorded_by'); // Core user ID.
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 300)->nullable();
            $table->timestamps();
            $table->index(['patient_id', 'voided_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('center')->dropIfExists('patient_payments');
        Schema::connection('center')->dropIfExists('care_indications');
    }
};
