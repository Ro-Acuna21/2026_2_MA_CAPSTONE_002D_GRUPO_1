<?php

use Illuminate\Database\Migrations\Migration;
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

        if (DB::connection('center')->table('specialties')->exists()) {
            throw new RuntimeException(
                'Este centro contiene especialidades locales. Ejecuta primero la migración Core del catálogo global de especialidades.'
            );
        }

        // A migrated center can still have services and professionals. Those
        // references already contain global IDs; only the empty local catalog
        // is obsolete. The Core migration may already have dropped the FKs.
        if (DB::connection('center')->getDriverName() === 'pgsql') {
            foreach (['services_specialty_id_foreign', 'professional_specialty_specialty_fk'] as $constraint) {
                $table = $constraint === 'services_specialty_id_foreign' ? 'services' : 'professional_specialty';
                DB::connection('center')->statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
            }
        }
        Schema::connection('center')->drop('specialties');
    }

    public function down(): void
    {
        // Restoring local IDs after assigning global specialties would corrupt references.
        throw new RuntimeException('La transición de especialidades globales no se puede revertir automáticamente.');
    }
};
