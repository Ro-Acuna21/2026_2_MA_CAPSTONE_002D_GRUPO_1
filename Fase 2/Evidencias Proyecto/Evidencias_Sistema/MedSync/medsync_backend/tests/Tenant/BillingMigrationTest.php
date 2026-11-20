<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('allocates existing active payments to the oldest charges without changing balances', function () {
    $file = tempnam(sys_get_temp_dir(), 'medsync_legacy_billing_');
    try {
        config(['database.connections.center' => array_merge(config('database.connections.center'), [
            'driver' => 'sqlite', 'url' => null, 'database' => $file,
        ])]);
        DB::purge('center');
        $schema = Schema::connection('center');
        $schema->create('services', fn (Blueprint $t) => $t->id());
        $schema->create('care_indications', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('patient_id'); $t->unsignedBigInteger('amount_clp'); $t->string('status');
        });
        $schema->create('patient_payments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('patient_id'); $t->unsignedBigInteger('amount_clp'); $t->timestamp('voided_at')->nullable();
        });
        $db = DB::connection('center');
        $db->table('care_indications')->insert([
            ['id' => 1, 'patient_id' => 7, 'amount_clp' => 10000, 'status' => 'CHARGED'],
            ['id' => 2, 'patient_id' => 7, 'amount_clp' => 8000, 'status' => 'CHARGED'],
        ]);
        $db->table('patient_payments')->insert(['id' => 1, 'patient_id' => 7, 'amount_clp' => 13000]);
        (require database_path('migrations/center/2026_11_20_000001_link_services_and_allocate_payments.php'))->up();
        expect($db->table('patient_payment_allocations')->orderBy('care_indication_id')->pluck('amount_clp')->all())
            ->toBe([10000, 3000]);
        expect((int) $db->table('patient_payment_allocations')->sum('amount_clp'))->toBe(13000);
    } finally {
        DB::purge('center');
        if (is_file($file)) unlink($file);
    }
});
