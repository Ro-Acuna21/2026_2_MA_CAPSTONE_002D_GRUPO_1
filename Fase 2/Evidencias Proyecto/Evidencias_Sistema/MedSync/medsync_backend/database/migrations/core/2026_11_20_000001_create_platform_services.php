<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('core')->create('platform_services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('category', 20);
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['category', 'name']);
        });
        Schema::connection('core')->create('platform_service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_service_id')->constrained('platform_services')->restrictOnDelete();
            $table->unsignedBigInteger('amount_clp');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->index(['platform_service_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('platform_service_prices');
        Schema::connection('core')->dropIfExists('platform_services');
    }
};
