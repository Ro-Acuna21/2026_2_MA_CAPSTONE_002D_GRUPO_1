<?php

namespace App\Services\Tenants;

use App\Models\Core\MedicalCenter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Configura la conexión clínica para el centro que ya fue autorizado.
 */
class TenantConnectionResolver
{
    public function connect(MedicalCenter $medicalCenter): void
    {
        $databaseName = trim((string) $medicalCenter->database_name);

        if ($databaseName === '') {
            throw new InvalidArgumentException('El centro médico no tiene una base clínica configurada.');
        }

        $configuredDatabase = config('database.connections.center.database');

        if (
            $configuredDatabase === $databaseName
            && DB::connection('center')->getDatabaseName() === $databaseName
        ) {
            return;
        }

        // El nombre viene exclusivamente de Core; nunca de URL, headers o payload.
        config(['database.connections.center.database' => $databaseName]);

        // Laravel almacena conexiones abiertas por nombre. Purgar evita reutilizar
        // la conexión del centro atendido por una petición anterior.
        DB::purge('center');
        DB::reconnect('center');
    }
}
