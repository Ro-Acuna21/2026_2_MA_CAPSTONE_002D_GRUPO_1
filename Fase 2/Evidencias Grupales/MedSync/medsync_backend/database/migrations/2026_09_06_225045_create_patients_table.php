<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_center_id')->constrained('medical_centers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('health_insurance_id')->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('rut', 12);
            $table->date('birth_date')->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address', 200)->nullable();
            $table->string('medical_insurance', 100)->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->string('consent_version', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['medical_center_id', 'rut']);
            $table->unique(['medical_center_id', 'email']);
            $table->index(['medical_center_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};