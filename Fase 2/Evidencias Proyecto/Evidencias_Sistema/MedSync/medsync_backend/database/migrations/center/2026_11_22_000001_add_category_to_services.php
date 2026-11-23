<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('center')->table('services', function (Blueprint $table) {
            // Nullable only for legacy rows; new and edited services are validated as required.
            $table->string('category', 20)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::connection('center')->table('services')->whereNotNull('category')->exists()) {
            throw new RuntimeException('No se puede retirar la categoría: hay prestaciones clasificadas.');
        }
        Schema::connection('center')->table('services', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
