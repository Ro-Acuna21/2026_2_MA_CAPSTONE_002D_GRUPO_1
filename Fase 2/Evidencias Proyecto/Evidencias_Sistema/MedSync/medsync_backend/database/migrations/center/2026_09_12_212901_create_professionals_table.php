<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('center')->create('professionals', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('rut', 12);
            $table->string('email', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('rut');
            $table->unique('email');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::connection('center')->dropIfExists('professionals');
    }
};