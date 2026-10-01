<?php

namespace Tests;

/**
 * El resolver purga la conexión center por petición. Estas pruebas mantienen
 * transacciones solo en Core y usan archivos SQLite temporales para tenants.
 */
abstract class TenantIsolationTestCase extends TestCase
{
    /** @var array<int, string> */
    protected array $connectionsToTransact = ['core'];
}
