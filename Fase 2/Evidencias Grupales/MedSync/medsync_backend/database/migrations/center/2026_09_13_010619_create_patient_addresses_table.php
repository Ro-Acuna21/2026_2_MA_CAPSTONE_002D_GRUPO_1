<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('center')->create('patient_addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();

            $table->string('address_line', 200);
            $table->string('commune', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('reference', 200)->nullable();
            $table->boolean('is_primary')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('patient_id');
            $table->index(['commune', 'region']);
        });

        if (Schema::connection('center')->hasColumn('patients', 'address')) {
            DB::connection('center')->statement("
                INSERT INTO patient_addresses (
                    patient_id,
                    address_line,
                    is_primary,
                    created_at,
                    updated_at
                )
                SELECT
                    id,
                    address,
                    true,
                    NOW(),
                    NOW()
                FROM patients
                WHERE address IS NOT NULL
                AND address <> ''
            ");

            Schema::connection('center')->table('patients', function (Blueprint $table) {
                $table->dropColumn('address');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('center')->table('patients', function (Blueprint $table) {
            $table->string('address', 200)->nullable();
        });

        DB::connection('center')->statement("
            UPDATE patients
            SET address = patient_addresses.address_line
            FROM patient_addresses
            WHERE patient_addresses.patient_id = patients.id
            AND patient_addresses.is_primary = true
            AND patient_addresses.deleted_at IS NULL
        ");

        Schema::connection('center')->dropIfExists('patient_addresses');
    }
};