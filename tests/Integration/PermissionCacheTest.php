<?php

declare(strict_types=1);

use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// La cache de permisos no puede sobrevivir a un cambio de tenant.
// RONDA-PLAN-MAESTRO.md sec. 7.4
//
// PermissionRegistrar es un singleton y guarda los permisos cargados en una
// propiedad de instancia. Bajo Octane el worker vive entre peticiones, asi que
// sin un reseteo explicito la peticion del cliente B resolveria permisos con
// los que cargo el cliente A.

function tenantDePrueba(string $slug): Tenant
{
    return resolve(CreateTenant::class)(new CreateTenantData(
        name: ucfirst($slug),
        slug: $slug,
        domain: $slug.'.ronda.test',
        ownerName: 'Duena de '.$slug,
        ownerEmail: 'owner@'.$slug.'.test',
        ownerPassword: 'una-contrasena-larga-de-prueba',
    ));
}

it('no arrastra los permisos de un tenant al siguiente', function (): void {
    $a = tenantDePrueba('alfa');
    $b = tenantDePrueba('beta');

    // El cliente A define un permiso propio y fuerza su carga en memoria.
    $a->run(function (): void {
        Permission::findOrCreate('solo.de.alfa', 'web');
        resolve(PermissionRegistrar::class)->getPermissions();
    });

    $b->run(function (): void {
        $nombres = resolve(PermissionRegistrar::class)
            ->getPermissions()
            ->pluck('name')
            ->all();

        // Sin mensaje: toContain() es variadico en Pest y un segundo argumento
        // se toma como otro valor a buscar, no como texto de error. Con el
        // mensaje puesto, la asercion pasaba sin comprobar nada.
        expect($nombres)->not->toContain('solo.de.alfa');
    });

    dropTenantDatabase($a);
    dropTenantDatabase($b);
})->group('tenancy');
