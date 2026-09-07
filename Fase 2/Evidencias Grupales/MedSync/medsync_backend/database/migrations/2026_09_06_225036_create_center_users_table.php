<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('center_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_center_id')->constrained('medical_centers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['ADMIN', 'RECEPCIONISTA', 'PROFESIONAL', 'PACIENTE']);
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('professional_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['medical_center_id', 'user_id']);
            $table->index(['medical_center_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('center_users');
    }
};
