<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('core')->table('medical_centers', function (Blueprint $table) {
            $table->string('plan', 20)->nullable();
            $table->string('subscription_status', 20)->nullable();
            $table->timestamp('subscription_ends_at')->nullable();

            $table->index(
                ['is_active', 'subscription_status'],
                'medical_centers_active_subscription_status_index'
            );
        });

        if (DB::connection('core')->getDriverName() === 'pgsql') {
            DB::connection('core')->statement(
                "ALTER TABLE medical_centers
                ADD CONSTRAINT medical_centers_valid_plan
                CHECK (plan IS NULL OR plan IN ('STARTER', 'PRO'))"
            );
            DB::connection('core')->statement(
                "ALTER TABLE medical_centers
                ADD CONSTRAINT medical_centers_valid_subscription_status
                CHECK (subscription_status IS NULL OR subscription_status IN ('TRIAL', 'ACTIVE', 'EXPIRED', 'SUSPENDED'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection('core')->getDriverName() === 'pgsql') {
            DB::connection('core')->statement(
                'ALTER TABLE medical_centers DROP CONSTRAINT IF EXISTS medical_centers_valid_subscription_status'
            );
            DB::connection('core')->statement(
                'ALTER TABLE medical_centers DROP CONSTRAINT IF EXISTS medical_centers_valid_plan'
            );
        }

        Schema::connection('core')->table('medical_centers', function (Blueprint $table) {
            $table->dropIndex('medical_centers_active_subscription_status_index');
            $table->dropColumn(['plan', 'subscription_status', 'subscription_ends_at']);
        });
    }
};
