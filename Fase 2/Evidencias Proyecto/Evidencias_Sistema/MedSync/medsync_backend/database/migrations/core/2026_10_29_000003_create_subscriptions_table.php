<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('core')->create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_center_id')
                ->constrained('medical_centers')
                ->restrictOnDelete();
            $table->foreignId('plan_id')
                ->constrained('plans')
                ->restrictOnDelete();
            $table->string('status', 20);
            $table->timestamp('started_at');
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

        });

        $driver = DB::connection('core')->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::connection('core')->statement(
                "CREATE UNIQUE INDEX subscriptions_one_open_per_center_unique
                ON subscriptions (medical_center_id)
                WHERE ended_at IS NULL"
            );
        }

        if ($driver === 'pgsql') {
            DB::connection('core')->statement(
                "ALTER TABLE subscriptions
                ADD CONSTRAINT subscriptions_valid_status
                CHECK (status IN ('TRIAL', 'ACTIVE', 'EXPIRED', 'SUSPENDED'))"
            );
        }
    }

    public function down(): void
    {
        $driver = DB::connection('core')->getDriverName();
        if ($driver === 'pgsql') {
            DB::connection('core')->statement(
                'ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_valid_status'
            );
        }
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::connection('core')->statement(
                'DROP INDEX IF EXISTS subscriptions_one_open_per_center_unique'
            );
        }

        Schema::connection('core')->dropIfExists('subscriptions');
    }
};
