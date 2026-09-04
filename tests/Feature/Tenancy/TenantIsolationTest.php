<?php

declare(strict_types=1);

// Aislamiento entre tenants. RONDA-PLAN-MAESTRO.md sec. 7.5
//
// Esta suite crece con cada modulo. En la fase 0 fija los invariantes de
// configuracion; a partir de la fase 1 recorre todas las rutas autenticadas
// con el usuario del tenant A apuntando a recursos del tenant B y exige 403
// o 404 en todas.

it('mantiene separadas la conexion central y la de tenant', function () {
    expect(config('database.default'))->toBe('pgsql')
        ->and(config('tenancy.database.central_connection'))->toBe('pgsql');
})->group('tenancy');

it('nombra las bases de tenant con un prefijo propio', function () {
    expect(config('tenancy.database.prefix'))
        ->not->toBeEmpty('Sin prefijo, una base de tenant puede colisionar con la central.');
})->group('tenancy');

it('no comparte la cache entre tenants', function () {
    expect(config('tenancy.cache.tag_base'))
        ->not->toBeEmpty('Sin etiqueta por tenant, la cache filtra datos entre clientes.');
})->group('tenancy');

todo('recorre todas las rutas autenticadas con un usuario de otro tenant y exige 403/404')
    ->group('tenancy');

todo('un job encolado en el tenant A no puede leer datos del tenant B')
    ->group('tenancy');

todo('una URL firmada de evidencia del tenant A se rechaza en el contexto del tenant B')
    ->group('tenancy');
