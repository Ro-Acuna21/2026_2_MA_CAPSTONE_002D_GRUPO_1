<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('center')->table('services', function (Blueprint $table) {
            // Logical Core ID: databases cannot have a cross-database foreign key.
            $table->unsignedBigInteger('platform_service_id')->nullable()->unique();
        });
        Schema::connection('center')->table('care_indications', function (Blueprint $table) {
            $table->string('price_source', 20)->nullable();
            $table->unsignedBigInteger('price_record_id')->nullable();
        });
        Schema::connection('center')->create('patient_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_payment_id')->constrained('patient_payments')->restrictOnDelete();
            $table->foreignId('care_indication_id')->constrained('care_indications')->restrictOnDelete();
            $table->unsignedBigInteger('amount_clp');
            $table->timestamps();
            $table->unique(['patient_payment_id', 'care_indication_id'], 'payment_charge_unique');
            $table->index('care_indication_id');
        });
        // Existing active payments had only a patient balance. Assign them oldest-charge-first
        // so historical balances remain unchanged and every charge obtains a determinable state.
        $db = DB::connection('center');
        foreach ($db->table('patient_payments')->whereNull('voided_at')->orderBy('id')->get() as $payment) {
            $remaining = (int) $payment->amount_clp;
            foreach ($db->table('care_indications')->where('patient_id', $payment->patient_id)->where('status', 'CHARGED')->orderBy('id')->get() as $charge) {
                $allocated = (int) $db->table('patient_payment_allocations as a')
                    ->join('patient_payments as p', 'p.id', '=', 'a.patient_payment_id')
                    ->whereNull('p.voided_at')->where('a.care_indication_id', $charge->id)->sum('a.amount_clp');
                $amount = min($remaining, (int) $charge->amount_clp - $allocated);
                if ($amount > 0) {
                    $db->table('patient_payment_allocations')->insert([
                        'patient_payment_id' => $payment->id, 'care_indication_id' => $charge->id,
                        'amount_clp' => $amount, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $remaining -= $amount;
                }
                if ($remaining === 0) break;
            }
            if ($remaining !== 0) throw new \RuntimeException('Hay un abono histórico sin cargos suficientes; revisa el centro antes de migrar.');
        }
    }

    public function down(): void
    {
        Schema::connection('center')->dropIfExists('patient_payment_allocations');
        Schema::connection('center')->table('care_indications', function (Blueprint $table) {
            $table->dropColumn(['price_source', 'price_record_id']);
        });
        Schema::connection('center')->table('services', function (Blueprint $table) {
            $table->dropUnique(['platform_service_id']);
            $table->dropColumn('platform_service_id');
        });
    }
};
