<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Ronda\Platform\Domain\Models\Tenant;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

// Integration NO usa RefreshDatabase: sus pruebas provisionan tenants de
// verdad, y `CREATE DATABASE` no puede correr dentro de una transaccion.
// Cada prueba deja la base central limpia con migrate:fresh y borra las bases
// de tenant que haya creado.
pest()->extend(TestCase::class)
    ->use(DatabaseMigrations::class)
    ->in('Integration');

/**
 * Borra la base de un tenant creado por una prueba.
 *
 * Hay que terminar la tenancy y purgar la conexion `tenant` antes: mientras
 * siga abierta, PostgreSQL rechaza el DROP con "cannot drop the currently open
 * database", y la prueba deja la base huerfana en el servidor.
 */
function dropTenantDatabase(Tenant $tenant): void
{
    $base = $tenant->databaseName();

    tenancy()->end();
    DB::purge('tenant');

    DB::connection(config('tenancy.database.central_connection'))
        ->statement('DROP DATABASE IF EXISTS "'.$base.'"');
}
