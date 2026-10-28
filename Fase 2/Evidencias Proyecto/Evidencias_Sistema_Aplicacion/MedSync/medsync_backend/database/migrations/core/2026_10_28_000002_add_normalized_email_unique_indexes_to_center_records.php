<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TENANT_CONNECTION = 'center_email_index_migration';

    public function up(): void
    {
        $centers = DB::connection('core')->table('medical_centers')
            ->get(['id', 'database_name']);

        // Check every existing tenant first so known duplicate data does not
        // leave only some centers with the new indexes.
        foreach ($centers as $center) {
            $this->useTenantDatabase($center->database_name);
            $this->assertNoNormalizedEmailDuplicates($center->database_name, 'patients');
            $this->assertNoNormalizedEmailDuplicates($center->database_name, 'professionals');
        }

        foreach ($centers as $center) {
            $this->useTenantDatabase($center->database_name);

            if (DB::connection(self::TENANT_CONNECTION)->getDriverName() !== 'pgsql') {
                continue;
            }

            DB::connection(self::TENANT_CONNECTION)->transaction(function () {
                foreach (['patients', 'professionals'] as $table) {
                    // The former single-column unique index compares raw email
                    // values and also reserves emails on soft-deleted rows.
                    DB::connection(self::TENANT_CONNECTION)->statement(
                        "ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_email_unique"
                    );
                    DB::connection(self::TENANT_CONNECTION)->statement(
                        "DROP INDEX IF EXISTS {$table}_email_unique"
                    );

                    DB::connection(self::TENANT_CONNECTION)->statement(
                        "CREATE UNIQUE INDEX IF NOT EXISTS {$table}_email_normalized_active_unique\n"
                        ."ON {$table} (LOWER(BTRIM(email)))\n"
                        .'WHERE email IS NOT NULL AND deleted_at IS NULL'
                    );
                }
            });
        }
    }

    public function down(): void
    {
        $centers = DB::connection('core')->table('medical_centers')
            ->get(['id', 'database_name']);

        // Rollback restores the old rule, which also covered deleted rows.
        // Refuse to remove the normalized indexes if new data would violate it.
        foreach ($centers as $center) {
            $this->useTenantDatabase($center->database_name);
            $this->assertNoRawEmailDuplicates($center->database_name, 'patients');
            $this->assertNoRawEmailDuplicates($center->database_name, 'professionals');
        }

        foreach ($centers as $center) {
            $this->useTenantDatabase($center->database_name);

            if (DB::connection(self::TENANT_CONNECTION)->getDriverName() !== 'pgsql') {
                continue;
            }

            DB::connection(self::TENANT_CONNECTION)->transaction(function () {
                foreach (['patients', 'professionals'] as $table) {
                    DB::connection(self::TENANT_CONNECTION)->statement(
                        "DROP INDEX IF EXISTS {$table}_email_normalized_active_unique"
                    );
                    DB::connection(self::TENANT_CONNECTION)->statement(
                        "CREATE UNIQUE INDEX IF NOT EXISTS {$table}_email_unique ON {$table} (email)"
                    );
                }
            });
        }
    }

    private function useTenantDatabase(string $databaseName): void
    {
        if (trim($databaseName) === '') {
            throw new RuntimeException('Hay un centro sin database_name en Core.');
        }

        $connection = config('database.connections.center');
        $connection['database'] = $databaseName;
        config(['database.connections.'.self::TENANT_CONNECTION => $connection]);
        DB::purge(self::TENANT_CONNECTION);

        foreach (['patients', 'professionals'] as $table) {
            if (! Schema::connection(self::TENANT_CONNECTION)->hasTable($table)) {
                throw new RuntimeException(
                    "No se puede actualizar {$databaseName}: falta la tabla {$table}."
                );
            }
        }
    }

    private function assertNoNormalizedEmailDuplicates(string $databaseName, string $table): void
    {
        if (DB::connection(self::TENANT_CONNECTION)->getDriverName() !== 'pgsql') {
            return;
        }

        $duplicateGroups = DB::connection(self::TENANT_CONNECTION)->table($table)
            ->selectRaw('LOWER(BTRIM(email)) AS normalized_email')
            ->whereNotNull('email')
            ->whereNull('deleted_at')
            ->groupByRaw('LOWER(BTRIM(email))')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($duplicateGroups > 0) {
            throw new RuntimeException(
                "{$databaseName}.{$table} tiene {$duplicateGroups} grupo(s) de correos activos "
                .'duplicados al normalizarlos. Corrígelos antes de ejecutar esta migración.'
            );
        }
    }

    private function assertNoRawEmailDuplicates(string $databaseName, string $table): void
    {
        if (DB::connection(self::TENANT_CONNECTION)->getDriverName() !== 'pgsql') {
            return;
        }

        $duplicateGroups = DB::connection(self::TENANT_CONNECTION)->table($table)
            ->select('email')
            ->whereNotNull('email')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($duplicateGroups > 0) {
            throw new RuntimeException(
                "No se puede revertir: {$databaseName}.{$table} tiene {$duplicateGroups} grupo(s) "
                .'de correos repetidos entre fichas activas o eliminadas.'
            );
        }
    }
};
