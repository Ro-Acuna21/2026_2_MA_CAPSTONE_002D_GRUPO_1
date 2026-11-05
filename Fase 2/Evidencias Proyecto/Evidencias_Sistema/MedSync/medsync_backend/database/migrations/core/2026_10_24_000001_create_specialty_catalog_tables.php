<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('core')->create('specialties', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active', 'specialties_is_active_index');
        });

        Schema::connection('core')->create('medical_center_specialties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_center_id')
                ->constrained('medical_centers')
                ->restrictOnDelete();
            $table->foreignId('specialty_id')
                ->constrained('specialties')
                ->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['medical_center_id', 'specialty_id'],
                'medical_center_specialties_center_specialty_unique'
            );
            $table->index(
                ['medical_center_id', 'is_active'],
                'medical_center_specialties_center_active_index'
            );
        });

        if (DB::connection('core')->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection('core')->statement(
            "ALTER TABLE specialties ADD CONSTRAINT specialties_name_not_blank CHECK (BTRIM(name) <> '')"
        );

        DB::connection('core')->statement(
            'CREATE UNIQUE INDEX specialties_name_unique_ci ON specialties (LOWER(BTRIM(name)))'
        );
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('medical_center_specialties');

        if (DB::connection('core')->getDriverName() === 'pgsql') {
            DB::connection('core')->statement('DROP INDEX IF EXISTS specialties_name_unique_ci');
        }

        Schema::connection('core')->dropIfExists('specialties');
    }
};
