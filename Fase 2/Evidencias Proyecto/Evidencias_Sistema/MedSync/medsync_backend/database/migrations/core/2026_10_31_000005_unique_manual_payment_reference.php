<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('core')->table('subscription_payments', function (Blueprint $table) {
            $table->unique(['subscription_id', 'provider_reference'], 'subscription_payments_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('core')->table('subscription_payments', function (Blueprint $table) {
            $table->dropUnique('subscription_payments_reference_unique');
        });
    }
};
