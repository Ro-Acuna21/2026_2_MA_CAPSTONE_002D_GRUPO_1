<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Normaliza los correos existentes para que las cuentas
         * globales de MedSync almacenen el email en minúsculas
         * y sin espacios exteriores.
         */
        DB::connection('core')->statement(
            '
            UPDATE users
            SET email = LOWER(BTRIM(email))
            WHERE email IS NOT NULL
            '
        );

        /*
         * Cada correo identifica una única cuenta global activa.
         *
         * La expresión LOWER(BTRIM(email)) evita diferencias por
         * mayúsculas, minúsculas o espacios exteriores.
         *
         * Los usuarios eliminados mediante soft delete quedan fuera
         * de esta restricción.
         */
        DB::connection('core')->statement(
            '
            CREATE UNIQUE INDEX users_email_unique_ci
            ON users (LOWER(BTRIM(email)))
            WHERE deleted_at IS NULL
            '
        );
    }

    public function down(): void
    {
        DB::connection('core')->statement(
            'DROP INDEX IF EXISTS users_email_unique_ci'
        );
    }
};