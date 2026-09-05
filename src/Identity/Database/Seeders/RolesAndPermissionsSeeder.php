<?php

declare(strict_types=1);

namespace Ronda\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Ronda\Identity\Domain\PermissionName;
use Ronda\Identity\Domain\RoleName;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles y permisos base de un tenant recien provisionado.
 * RONDA-PLAN-MAESTRO.md sec. 7.2
 *
 * Es idempotente: se puede volver a ejecutar sobre un tenant existente para
 * incorporar permisos nuevos sin tocar los roles que el cliente haya ajustado.
 */
final class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // El registrar cachea permisos por peticion. Sin vaciarlo, sembrar y
        // asignar en el mismo proceso asigna contra una lista obsoleta.
        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value, 'web');

            $role->syncPermissions(
                array_map(
                    static fn (PermissionName $permission): string => $permission->value,
                    $roleName->defaultPermissions(),
                ),
            );
        }

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
