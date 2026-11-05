<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function pendingMigrations(string $connection, string $directory, ?string $ledgerConnection = null): array
{
    $files = glob(database_path('migrations/'.$directory.'/*.php')) ?: [];
    $expected = array_map(fn ($file) => basename($file, '.php'), $files);
    $ledger = $ledgerConnection ?? $connection;
    $ran = Schema::connection($ledger)->hasTable('migrations')
        ? DB::connection($ledger)->table('migrations')->pluck('migration')->all()
        : [];
    return array_values(array_diff($expected, $ran));
}

try {
    echo 'Core: conectado; pendientes: '.implode(', ', pendingMigrations('core', 'core')).PHP_EOL;
    $centers = DB::connection('core')->table('medical_centers')->get(['id', 'slug', 'database_name']);
    $original = config('database.connections.center');
    foreach ($centers as $center) {
        try {
            if (! preg_match('/^[a-zA-Z0-9_]+$/', $center->database_name)) {
                throw new RuntimeException('Nombre de base inválido en Core');
            }
            config(['database.connections.center.database' => $center->database_name]);
            DB::purge('center');
            $localLedger = Schema::connection('center')->hasTable('migrations');
            $pending = pendingMigrations('center', 'center', $localLedger ? null : 'core');
            $missingTables = array_values(array_filter(
                ['patients', 'professionals', 'services', 'appointments'],
                fn ($table) => ! Schema::connection('center')->hasTable($table)
            ));
            $localSpecialties = Schema::connection('center')->hasTable('specialties')
                ? DB::connection('center')->table('specialties')->count() : 0;
            $invalidMemberships = 0;
            if (Schema::connection('center')->hasTable('patients')) {
                $memberships = DB::connection('core')->table('center_users as cu')
                    ->join('users as u', 'u.id', '=', 'cu.user_id')
                    ->where('cu.medical_center_id', $center->id)->where('cu.role', 'PACIENTE')
                    ->where('cu.is_active', true)->get(['cu.patient_id', 'cu.user_id', 'u.email']);
                foreach ($memberships as $membership) {
                    $patient = $membership->patient_id
                        ? DB::connection('center')->table('patients')->where('id', $membership->patient_id)
                            ->whereNull('deleted_at')->first(['user_id', 'email', 'is_active']) : null;
                    if (! $patient || ! $patient->is_active || (int) $patient->user_id !== (int) $membership->user_id
                        || strtolower(trim((string) $patient->email)) !== strtolower(trim((string) $membership->email))) {
                        $invalidMemberships++;
                    }
                }
            }
            echo "Centro {$center->id} ({$center->slug}): conectado; registro ".($localLedger ? 'local' : 'legado Core')
                .'; tablas faltantes: '.implode(', ', $missingTables)
                .'; especialidades locales: '.$localSpecialties
                .'; membresías de paciente inválidas: '.$invalidMemberships
                .'; pendientes: '.implode(', ', $pending).PHP_EOL;
        } catch (Throwable $exception) {
            echo "Centro {$center->id} ({$center->slug}): ERROR ".$exception->getMessage().PHP_EOL;
        }
    }
    config(['database.connections.center' => $original]);
    DB::purge('center');
} catch (Throwable $exception) {
    fwrite(STDERR, 'Core: ERROR '.$exception->getMessage().PHP_EOL);
    exit(1);
}
