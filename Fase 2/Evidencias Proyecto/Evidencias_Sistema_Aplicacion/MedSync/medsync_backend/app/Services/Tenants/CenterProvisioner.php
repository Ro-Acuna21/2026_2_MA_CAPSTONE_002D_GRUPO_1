<?php

namespace App\Services\Tenants;

use App\Models\Core\MedicalCenter;
use Database\Seeders\CenterHealthInsuranceSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class CenterProvisioner
{
    public function provision(MedicalCenter $center, bool $activate = true): void
    {
        if ($center->provisioning_status === 'READY') {
            return;
        }

        $center->update(['provisioning_status' => 'PENDING', 'is_active' => false]);
        $originalConnection = config('database.connections.center');

        try {
            $this->createDatabaseIfMissing($center->database_name);
            config(['database.connections.center.database' => $center->database_name]);
            DB::purge('center');

            $migrationResult = Artisan::call('migrate', [
                '--database' => 'center',
                '--path' => 'database/migrations/center',
                '--force' => true,
            ]);
            if ($migrationResult !== 0) {
                throw new RuntimeException('No se pudieron aplicar las migraciones clínicas. '.Artisan::output());
            }

            $seedResult = Artisan::call('db:seed', [
                '--database' => 'center',
                '--class' => CenterHealthInsuranceSeeder::class,
                '--force' => true,
            ]);
            if ($seedResult !== 0) {
                throw new RuntimeException('No se pudo cargar el catálogo clínico inicial. '.Artisan::output());
            }

            foreach (['patients', 'professionals', 'health_insurances', 'services', 'appointments'] as $table) {
                if (! Schema::connection('center')->hasTable($table)) {
                    throw new RuntimeException("Falta la tabla clínica {$table} tras el aprovisionamiento.");
                }
            }

            $center->update([
                'provisioning_status' => 'READY',
                'provisioned_at' => now(),
                'is_active' => $activate,
            ]);
        } catch (Throwable $exception) {
            $center->update(['provisioning_status' => 'FAILED', 'is_active' => false]);
            throw $exception;
        } finally {
            config(['database.connections.center' => $originalConnection]);
            DB::purge('center');
        }
    }

    protected function createDatabaseIfMissing(string $databaseName): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]{0,62}$/', $databaseName)) {
            throw new RuntimeException('El nombre de base clínica almacenado no es válido.');
        }
        if (DB::connection('core')->getDriverName() !== 'pgsql') {
            throw new RuntimeException('El aprovisionamiento automático requiere PostgreSQL.');
        }

        $exists = DB::connection('core')->selectOne(
            'SELECT 1 AS present FROM pg_database WHERE datname = ?',
            [$databaseName]
        );
        if (! $exists) {
            DB::connection('core')->statement('CREATE DATABASE "'.$databaseName.'"');
        }
    }
}
