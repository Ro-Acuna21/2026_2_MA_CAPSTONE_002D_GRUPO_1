<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('center')->table('patient_payments', function (Blueprint $table) {
            $table->string('idempotency_key', 80)->nullable();
            $table->char('request_hash', 64)->nullable();
            $table->unique(['patient_id', 'idempotency_key'], 'patient_payment_idempotency_unique');
        });
    }

    public function down(): void
    {
        if (DB::connection('center')->table('patient_payments')->whereNotNull('idempotency_key')->exists()) {
            throw new RuntimeException('No se puede retirar la protección de reintentos mientras existan pagos identificados.');
        }
        Schema::connection('center')->table('patient_payments', function (Blueprint $table) {
            $table->dropUnique('patient_payment_idempotency_unique');
            $table->dropColumn(['idempotency_key', 'request_hash']);
        });
    }
};
