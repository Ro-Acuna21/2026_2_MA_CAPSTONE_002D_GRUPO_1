<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('core')->create('commercial_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('contact_name', 150);
            $table->string('center_name', 150);
            $table->string('email', 150);
            $table->string('phone', 30)->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('commercial_inquiries');
    }
};
