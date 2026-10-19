<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('center')->create('result_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if (DB::connection('center')->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection('center')->statement(
            "ALTER TABLE result_types
            ADD CONSTRAINT result_types_name_not_blank
            CHECK (BTRIM(name) <> '')"
        );

        DB::connection('center')->statement(
            "CREATE UNIQUE INDEX result_types_name_unique_ci
            ON result_types (LOWER(BTRIM(name)))"
        );
    }

    public function down(): void
    {
        Schema::connection('center')->dropIfExists('result_types');
    }
};
