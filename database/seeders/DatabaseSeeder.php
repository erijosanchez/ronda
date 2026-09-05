<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;

/**
 * Semilla de la base CENTRAL. Aqui no hay usuarios: los usuarios viven en la
 * base de cada tenant (sec. 8.3). Lo unico que siembra es un cliente de
 * desarrollo para poder entrar y probar el login de verdad.
 *
 * La semilla de dentro del tenant es TenantDatabaseSeeder.
 */
final class DatabaseSeeder extends Seeder
{
    private const string DEMO_SLUG = 'demo';

    private const string DEMO_DOMAIN = 'demo.localhost';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DatabaseSeeder solo siembra datos de desarrollo. Omitido en produccion.');

            return;
        }

        if (Tenant::query()->where('slug', self::DEMO_SLUG)->exists()) {
            $this->command?->info('El tenant de demostracion ya existe. Nada que hacer.');

            return;
        }

        $tenant = resolve(CreateTenant::class)(new CreateTenantData(
            name: 'Ronda Demo',
            slug: self::DEMO_SLUG,
            domain: self::DEMO_DOMAIN,
            ownerName: 'Propietario Demo',
            ownerEmail: 'owner@demo.test',
            ownerPassword: 'password-de-desarrollo',
        ));

        $this->command?->info("Tenant {$tenant->id} creado en http://".self::DEMO_DOMAIN.':8000');
        $this->command?->info('Acceso: owner@demo.test / password-de-desarrollo');
    }
}
