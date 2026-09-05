<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\PermissionName;
use Ronda\Identity\Domain\RoleName;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Events\TenantProvisioned;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Platform\Domain\States\Trial;

// Provision de un cliente de punta a punta. RONDA-PLAN-MAESTRO.md sec. 7.2
//
// Es el corazon del ADR 0002 y por eso se prueba contra PostgreSQL de verdad:
// una prueba con dobles no habria detectado que `CREATE DATABASE` no puede
// correr dentro de una transaccion, que es justo donde estaba el fallo.

function provisionar(string $slug = 'acme'): Tenant
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

it('crea el tenant en la base central con ULID, slug y estado de prueba', function (): void {
    $tenant = provisionar();

    expect($tenant->exists)->toBeTrue()
        ->and($tenant->id)->toHaveLength(26)          // ULID, no UUID de 36
        ->and($tenant->slug)->toBe('acme')
        ->and($tenant->status)->toBeInstanceOf(Trial::class)
        ->and($tenant->domains()->pluck('domain')->all())->toBe(['acme.ronda.test']);

    dropTenantDatabase($tenant);
})->group('tenancy');

it('crea la base de datos del tenant con el prefijo configurado', function (): void {
    $tenant = provisionar();
    $base = $tenant->databaseName();

    expect($base)->toStartWith(config('tenancy.database.prefix'));

    $existe = DB::selectOne('select 1 as hay from pg_database where datname = ?', [$base]);
    expect($existe)->not->toBeNull("La base {$base} no se creo.");

    dropTenantDatabase($tenant);
})->group('tenancy');

it('migra el esquema del tenant y siembra roles y permisos', function (): void {
    $tenant = provisionar();

    $tenant->run(function (): void {
        expect(Schema::hasTable('users'))->toBeTrue()
            ->and(Schema::hasTable('roles'))->toBeTrue();

        expect(DB::table('permissions')->count())
            ->toBe(count(PermissionName::cases()));

        expect(DB::table('roles')->pluck('name')->sort()->values()->all())
            ->toBe(collect(RoleName::cases())->pluck('value')->sort()->values()->all());
    });

    dropTenantDatabase($tenant);
})->group('tenancy');

it('crea al propietario dentro de la base del tenant y le asigna el rol owner', function (): void {
    $tenant = provisionar();

    $tenant->run(function (): void {
        $owner = User::query()->where('email', 'owner@acme.test')->first();

        expect($owner)->not->toBeNull()
            ->and($owner->name)->toBe('Duena de acme')
            // La contrasena se guarda hasheada por el cast del modelo.
            ->and($owner->password)->not->toBe('una-contrasena-larga-de-prueba')
            ->and($owner->roles->pluck('name')->all())->toBe([RoleName::Owner->value]);
    });

    dropTenantDatabase($tenant);
})->group('tenancy');

it('no deja usuarios en la base central', function (): void {
    // El invariante del ADR 0002: si `users` no existe en la central, ningun
    // scope olvidado puede filtrar usuarios entre clientes.
    $tenant = provisionar();

    expect(Schema::hasTable('users'))->toBeFalse(
        'La base central no debe tener tabla `users`: los usuarios viven en el tenant.',
    );

    dropTenantDatabase($tenant);
})->group('tenancy');

it('emite TenantProvisioned al terminar', function (): void {
    Event::fake([TenantProvisioned::class]);

    $tenant = provisionar();

    Event::assertDispatched(
        TenantProvisioned::class,
        fn (TenantProvisioned $evento): bool => $evento->tenant->is($tenant),
    );

    dropTenantDatabase($tenant);
})->group('tenancy');
