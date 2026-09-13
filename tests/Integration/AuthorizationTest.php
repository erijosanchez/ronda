<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\PermissionName;
use Ronda\Identity\Domain\RoleName;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;

// Autorizacion. RONDA-PLAN-MAESTRO.md sec. 10.3
//
// Corre dentro de un tenant provisionado porque roles y permisos viven en su
// base: sin sembrarlos, cualquier comprobacion daria false y la prueba pasaria
// por el motivo equivocado.

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: 'una-contrasena-larga-de-prueba',
    ));
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

/**
 * Ejecuta la comprobacion dentro del contexto del tenant.
 */
function enElTenant(Closure $fn): void
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;
    $tenant->run($fn);
}

function usuarioCon(RoleName $rol): User
{
    $user = User::create([
        'name' => 'Persona '.$rol->value,
        'email' => $rol->value.'@acme.test',
        'password' => 'una-contrasena-larga-de-prueba',
    ]);

    $user->assignRole($rol->value);

    return $user->fresh();
}

function propietario(): User
{
    return User::query()->where('email', 'owner@acme.test')->firstOrFail();
}

it('deja al propietario hacer cualquier cosa, incluso sin permiso declarado', function (): void {
    enElTenant(function (): void {
        $owner = propietario();

        // Una habilidad que no existe en ninguna Policy ni en la tabla de
        // permisos: Gate::before la concede igual.
        expect(Gate::forUser($owner)->allows('una-habilidad-que-no-existe'))->toBeTrue()
            ->and(Gate::forUser($owner)->allows('viewAny', User::class))->toBeTrue();
    });
})->group('auth');

it('concede a cada rol solo los permisos de su lista', function (): void {
    enElTenant(function (): void {
        $admin = usuarioCon(RoleName::Admin);
        $encargado = usuarioCon(RoleName::SiteManager);

        expect($admin->can(PermissionName::UserManage->value))->toBeTrue()
            ->and($admin->can(PermissionName::SubmissionApprove->value))->toBeFalse()
            ->and($encargado->can(PermissionName::SubmissionCreate->value))->toBeTrue()
            ->and($encargado->can(PermissionName::UserManage->value))->toBeFalse();
    });
})->group('auth');

it('solo deja administrar usuarios a quien tiene user.manage', function (): void {
    enElTenant(function (): void {
        $admin = usuarioCon(RoleName::Admin);
        $supervisor = usuarioCon(RoleName::Supervisor);

        expect(Gate::forUser($admin)->allows('create', User::class))->toBeTrue()
            ->and(Gate::forUser($supervisor)->allows('create', User::class))->toBeFalse();
    });
})->group('auth');

it('deja ver y editar la ficha propia sin ningun permiso', function (): void {
    enElTenant(function (): void {
        $encargado = usuarioCon(RoleName::SiteManager);

        expect(Gate::forUser($encargado)->allows('view', $encargado))->toBeTrue()
            ->and(Gate::forUser($encargado)->allows('update', $encargado))->toBeTrue()
            // Pero no la de otros: no tiene user.view.
            ->and(Gate::forUser($encargado)->allows('view', propietario()))->toBeFalse();
    });
})->group('auth');

it('impide que un administrador toque al propietario', function (): void {
    enElTenant(function (): void {
        $admin = usuarioCon(RoleName::Admin);
        $owner = propietario();

        // Sin esto, un admin le cambia el correo al dueno y se queda la cuenta.
        expect(Gate::forUser($admin)->allows('update', $owner))->toBeFalse()
            ->and(Gate::forUser($admin)->allows('delete', $owner))->toBeFalse();
    });
})->group('auth');

it('no deja que nadie se borre a si mismo', function (): void {
    enElTenant(function (): void {
        $admin = usuarioCon(RoleName::Admin);

        expect(Gate::forUser($admin)->allows('delete', $admin))->toBeFalse();
    });
})->group('auth');

it('deja a un administrador borrar a un usuario corriente', function (): void {
    enElTenant(function (): void {
        $admin = usuarioCon(RoleName::Admin);
        $encargado = usuarioCon(RoleName::SiteManager);

        expect(Gate::forUser($admin)->allows('delete', $encargado))->toBeTrue();
    });
})->group('auth');

it('deniega por defecto a un usuario sin roles', function (): void {
    enElTenant(function (): void {
        $sinRol = User::create([
            'name' => 'Sin rol',
            'email' => 'sinrol@acme.test',
            'password' => 'una-contrasena-larga-de-prueba',
        ]);

        expect(Gate::forUser($sinRol)->allows('viewAny', User::class))->toBeFalse()
            ->and(Gate::forUser($sinRol)->allows('create', User::class))->toBeFalse();
    });
})->group('auth');
