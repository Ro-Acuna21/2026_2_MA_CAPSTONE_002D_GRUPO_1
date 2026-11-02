<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('center')->hasTable('specialties')) {
            // Existing centers were converted by the Core catalog migration.
            return;
        }

        if (DB::connection('center')->table('specialties')->exists()
            || DB::connection('center')->table('services')->exists()
            || DB::connection('center')->table('professional_specialty')->exists()) {
            throw new RuntimeException(
                'Este centro contiene especialidades locales. Ejecuta primero la migración Core del catálogo global de especialidades.'
            );
        }

        Schema::connection('center')->table('services', function (Blueprint $table) {
            $table->dropForeign('services_specialty_id_foreign');
        });
        Schema::connection('center')->table('professional_specialty', function (Blueprint $table) {
            $table->dropForeign('professional_specialty_specialty_fk');
        });
        Schema::connection('center')->drop('specialties');
    }

    public function down(): void
    {
        // Restoring local IDs after assigning global specialties would corrupt references.
        throw new RuntimeException('La transición de especialidades globales no se puede revertir automáticamente.');
    }
};
