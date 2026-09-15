<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('core')->create('medical_center_addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('medical_center_id')
                ->constrained('medical_centers')
                ->cascadeOnDelete();

            $table->string('address_line', 200);
            $table->string('commune', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('reference', 200)->nullable();
            $table->boolean('is_primary')->default(true);
            $table->timestamps();

            $table->index('medical_center_id');
            $table->index(['commune', 'region']);
        });

        if (Schema::connection('core')->hasColumn('medical_centers', 'address')) {
            DB::connection('core')->statement("
                INSERT INTO medical_center_addresses (
                    medical_center_id,
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
                FROM medical_centers
                WHERE address IS NOT NULL
                AND address <> ''
            ");

            Schema::connection('core')->table('medical_centers', function (Blueprint $table) {
                $table->dropColumn('address');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('core')->table('medical_centers', function (Blueprint $table) {
            $table->string('address', 200)->nullable();
        });

        DB::connection('core')->statement("
            UPDATE medical_centers
            SET address = medical_center_addresses.address_line
            FROM medical_center_addresses
            WHERE medical_center_addresses.medical_center_id = medical_centers.id
            AND medical_center_addresses.is_primary = true
        ");

        Schema::connection('core')->dropIfExists('medical_center_addresses');
    }
};