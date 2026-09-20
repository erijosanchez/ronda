<?php

declare(strict_types=1);

use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;

// El CSS y el JS dentro de un cliente.
//
// Esto no lo destapo ninguna prueba: todas miraban el codigo de respuesta y la
// pagina respondia 200, pero sin estilos. Lo vio una persona abriendo el login.
//
// La causa era `asset_helper_tenancy`, que reescribe toda llamada a `asset()`
// —incluidas las de `@vite`— hacia `/tenancy/assets/...`, la ruta que sirve el
// almacenamiento privado del cliente. El bundle no vive ahi, asi que devolvia
// 404 y la aplicacion se veia en crudo en el dominio de CADA cliente. En el
// central se veia bien, que es por lo que tardo en notarse.

const CLAVE_ASSETS = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_ASSETS,
    ));
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

it('sirve el bundle desde public y no desde el almacen privado del cliente', function (): void {
    $html = $this->get('http://acme.ronda.test/login')->assertOk()->content();

    expect($html)->not->toContain('/tenancy/assets/')
        ->and($html)->toContain('/build/assets/');
})->group('tenancy');
