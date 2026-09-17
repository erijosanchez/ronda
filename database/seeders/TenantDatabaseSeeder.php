<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Ronda\Directory\Database\Seeders\PositionsSeeder;
use Ronda\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use Ronda\Scheduling\Database\Seeders\HolidaysSeeder;

/**
 * Semilla que corre DENTRO de la base de cada tenant, no en la central.
 * La invoca ProvisionTenantJob y tambien `php artisan tenants:seed`.
 *
 * Solo catalogos y datos de arranque: ningun usuario. El propietario lo crea
 * la provision, que es quien conoce sus datos.
 */
final class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(PositionsSeeder::class);
        $this->call(HolidaysSeeder::class);
    }
}
