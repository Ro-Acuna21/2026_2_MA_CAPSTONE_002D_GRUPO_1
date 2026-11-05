<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('core')->create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 40);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('monthly_price_clp');
            $table->unsignedInteger('professional_limit')->nullable();
            $table->unsignedInteger('user_limit')->nullable();
            $table->jsonb('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('plans');
    }
};
