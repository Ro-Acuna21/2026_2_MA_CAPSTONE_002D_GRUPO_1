<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('core')->create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')
                ->constrained('subscriptions')
                ->restrictOnDelete();
            $table->unsignedBigInteger('amount_clp');
            $table->string('status', 20);
            $table->timestamp('due_at');
            $table->timestamp('paid_at')->nullable();
            $table->string('provider_reference', 255)->nullable();
            $table->timestamps();

        });

        if (DB::connection('core')->getDriverName() === 'pgsql') {
            DB::connection('core')->statement(
                "ALTER TABLE subscription_payments
                ADD CONSTRAINT subscription_payments_valid_status
                CHECK (status IN ('PENDING', 'PAID', 'OVERDUE'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection('core')->getDriverName() === 'pgsql') {
            DB::connection('core')->statement(
                'ALTER TABLE subscription_payments DROP CONSTRAINT IF EXISTS subscription_payments_valid_status'
            );
        }

        Schema::connection('core')->dropIfExists('subscription_payments');
    }
};
