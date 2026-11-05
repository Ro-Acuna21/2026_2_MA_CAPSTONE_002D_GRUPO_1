<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('core')->table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('monthly_price_clp_snapshot')->nullable();
        });
        Schema::connection('core')->table('subscription_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('recorded_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('core')->table('subscription_payments', fn (Blueprint $table) => $table->dropColumn('recorded_by'));
        Schema::connection('core')->table('subscriptions', fn (Blueprint $table) => $table->dropColumn('monthly_price_clp_snapshot'));
    }
};
