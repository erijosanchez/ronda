<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Ronda\Identity\Application\Actions\DeleteUser;
use Ronda\Identity\Domain\Exceptions\CannotDeleteLastOwner;
use Ronda\Identity\Domain\Exceptions\CannotDeleteSelf;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\RoleName;
use Ronda\Identity\Domain\UserStatus;
use Ronda\Identity\Presentation\Livewire\UserForm;
use Ronda\Identity\Presentation\Livewire\UserList;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;

// Gestion de personas del cliente. RONDA-PLAN-MAESTRO.md sec. 8.3 y 10.3

const CLAVE_USUARIOS = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_USUARIOS,
    ));
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function dentroDelTenant(Closure $fn): void
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;
    $tenant->run($fn);
}

function duena(): User
{
    $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();
    auth()->login($owner);

    return $owner;
}

function personaCon(RoleName $rol, string $email): User
{
    $user = User::create([
        'name' => 'Persona '.$rol->value,
        'email' => $email,
        'password' => CLAVE_USUARIOS,
    ]);
    $user->assignRole($rol->value);

    return $user->fresh();
}

it('crea una persona con sus roles', function (): void {
    dentroDelTenant(function (): void {
        duena();

        Livewire::test(UserForm::class)
            ->set('name', 'Ana Torres')
            ->set('email', 'ana@acme.test')
            ->set('password', 'una-contrasena-bien-larga')
            ->set('roles', [RoleName::Supervisor->value])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('users.index'));

        $ana = User::query()->where('email', 'ana@acme.test')->firstOrFail();

        expect($ana->name)->toBe('Ana Torres')
            ->and($ana->status)->toBe(UserStatus::Active)
            ->and($ana->roles->pluck('name')->all())->toBe([RoleName::Supervisor->value])
            // La contrasena se guarda hasheada por el cast del modelo.
            ->and($ana->password)->not->toBe('una-contrasena-bien-larga');
    });
})->group('directory');

it('guarda el telefono cifrado en la base', function (): void {
    dentroDelTenant(function (): void {
        duena();

        Livewire::test(UserForm::class)
            ->set('name', 'Luis Rojas')
            ->set('email', 'luis@acme.test')
            ->set('phone', '+51 999 888 777')
            ->set('password', 'una-contrasena-bien-larga')
            ->call('save')
            ->assertHasNoErrors();

        $luis = User::query()->where('email', 'luis@acme.test')->firstOrFail();

        // El modelo lo descifra...
        expect($luis->phone)->toBe('+51 999 888 777');

        // ...pero en la columna no esta en claro (sec. 8.6).
        $enBruto = DB::table('users')->where('email', 'luis@acme.test')->value('phone');

        expect($enBruto)->not->toBe('+51 999 888 777')
            ->and($enBruto)->not->toContain('999');
    });
})->group('directory');

it('exige contrasena al crear pero no al editar', function (): void {
    dentroDelTenant(function (): void {
        duena();

        Livewire::test(UserForm::class)
            ->set('name', 'Sin clave')
            ->set('email', 'sinclave@acme.test')
            ->call('save')
            ->assertHasErrors(['password' => 'required']);

        $existente = personaCon(RoleName::Admin, 'admin@acme.test');
        $hashAnterior = $existente->password;

        Livewire::test(UserForm::class, ['user' => $existente])
            ->set('name', 'Renombrada')
            ->call('save')
            ->assertHasNoErrors();

        // Dejarla vacia no la cambia.
        expect($existente->refresh()->password)->toBe($hashAnterior)
            ->and($existente->name)->toBe('Renombrada');
    });
})->group('directory');

it('no deja repetir el correo de otra persona', function (): void {
    dentroDelTenant(function (): void {
        duena();
        personaCon(RoleName::Admin, 'admin@acme.test');

        Livewire::test(UserForm::class)
            ->set('name', 'Otra')
            ->set('email', 'admin@acme.test')
            ->set('password', 'una-contrasena-bien-larga')
            ->call('save')
            ->assertHasErrors(['email' => 'unique']);
    });
})->group('directory');

it('rechaza una contrasena mas corta que el minimo de la politica', function (): void {
    dentroDelTenant(function (): void {
        duena();

        Livewire::test(UserForm::class)
            ->set('name', 'Corta')
            ->set('email', 'corta@acme.test')
            ->set('password', 'corta')
            ->call('save')
            ->assertHasErrors(['password']);
    });
})->group('directory');

it('da de baja con borrado logico y conserva la fila', function (): void {
    dentroDelTenant(function (): void {
        $owner = duena();
        $admin = personaCon(RoleName::Admin, 'admin@acme.test');

        resolve(DeleteUser::class)($owner, $admin);

        // Ya no aparece...
        expect(User::query()->where('email', 'admin@acme.test')->exists())->toBeFalse();

        // ...pero la fila sigue, para no perder su historial (sec. 8.6).
        expect(User::withTrashed()->where('email', 'admin@acme.test')->exists())->toBeTrue();
    });
})->group('directory');

it('no deja que el propietario se borre a si mismo, pese a Gate::before', function (): void {
    dentroDelTenant(function (): void {
        $owner = duena();

        // UserPolicy ya lo impide, pero OwnerGate devuelve true antes de que
        // llegue a ejecutarse: el invariante tiene que estar en la Action.
        expect($owner->can('delete', $owner))->toBeTrue();

        expect(fn () => resolve(DeleteUser::class)($owner, $owner))
            ->toThrow(CannotDeleteSelf::class);
    });
})->group('directory');

it('no deja al cliente sin propietario', function (): void {
    dentroDelTenant(function (): void {
        $owner = duena();
        $admin = personaCon(RoleName::Admin, 'admin@acme.test');

        expect(fn () => resolve(DeleteUser::class)($admin, $owner))
            ->toThrow(CannotDeleteLastOwner::class);

        expect(User::query()->whereKey($owner->getKey())->exists())->toBeTrue();
    });
})->group('directory');

it('deja dar de baja a un propietario si queda otro', function (): void {
    dentroDelTenant(function (): void {
        $owner = duena();
        $segundo = personaCon(RoleName::Owner, 'owner2@acme.test');

        resolve(DeleteUser::class)($segundo, $owner);

        expect(User::query()->whereKey($owner->getKey())->exists())->toBeFalse()
            ->and(User::query()->whereKey($segundo->getKey())->exists())->toBeTrue();
    });
})->group('directory');

it('traduce el invariante a un mensaje en el listado', function (): void {
    dentroDelTenant(function (): void {
        $owner = duena();

        // Desde la pantalla no revienta: se explica.
        Livewire::test(UserList::class)
            ->call('delete', $owner->getKey())
            ->assertHasErrors('delete');
    });
})->group('directory');

it('no deja gestionar usuarios a quien no tiene el permiso', function (): void {
    dentroDelTenant(function (): void {
        $supervisor = personaCon(RoleName::Supervisor, 'supervisor@acme.test');
        auth()->login($supervisor);

        // Puede verlos: tiene user.view? No, supervisor no lo tiene.
        Livewire::test(UserList::class)->assertForbidden();
        Livewire::test(UserForm::class)->assertForbidden();
    });
})->group('directory');

it('deja ver el listado a un administrador', function (): void {
    dentroDelTenant(function (): void {
        $admin = personaCon(RoleName::Admin, 'admin@acme.test');
        auth()->login($admin);

        Livewire::test(UserList::class)
            ->assertOk()
            ->assertSee('owner@acme.test');
    });
})->group('directory');
