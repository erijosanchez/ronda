<?php

declare(strict_types=1);

use Livewire\Livewire;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Directory\Domain\Models\Zone;
use Ronda\Directory\Presentation\Livewire\SiteForm;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\RoleName;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;

// Alta y edicion de sedes. RONDA-PLAN-MAESTRO.md sec. 8.3

const CLAVE_FORM = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_FORM,
    ));
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function enTenant(Closure $fn): void
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;
    $tenant->run($fn);
}

function comoPropietario(): User
{
    $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();
    auth()->login($owner);

    return $owner;
}

function comoEncargado(string $email, ?Site $sede = null): void
{
    $user = User::create([
        'name' => 'Encargado',
        'email' => $email,
        'password' => CLAVE_FORM,
    ]);
    $user->assignRole(RoleName::SiteManager->value);

    if ($sede instanceof Site) {
        $user->sites()->attach($sede->getKey());
    }

    auth()->login($user->fresh());
}

it('crea una sede con los datos del formulario', function (): void {
    enTenant(function (): void {
        comoPropietario();
        $zona = Zone::create(['name' => 'Lima Norte']);

        Livewire::test(SiteForm::class)
            ->set('code', 'S-100')
            ->set('name', 'Sede Nueva')
            ->set('zoneId', (string) $zona->id)
            ->set('timezone', 'America/Lima')
            ->set('opensAt', '08:30')
            ->set('closesAt', '19:00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('sites.index'));

        $sede = Site::query()->where('code', 'S-100')->firstOrFail();

        expect($sede->name)->toBe('Sede Nueva')
            ->and($sede->zone_id)->toBe($zona->id)
            ->and($sede->timezone)->toBe('America/Lima')
            // Postgres devuelve la hora completa, con segundos.
            ->and($sede->opens_at)->toBe('08:30:00');
    });
})->group('directory');

it('deja vacios los campos opcionales en vez de guardar cadenas vacias', function (): void {
    enTenant(function (): void {
        comoPropietario();

        Livewire::test(SiteForm::class)
            ->set('code', 'S-101')
            ->set('name', 'Sede Simple')
            ->call('save')
            ->assertHasNoErrors();

        $sede = Site::query()->where('code', 'S-101')->firstOrFail();

        // Una cadena vacia en una columna `date` revienta en Postgres, y en
        // `zone_id` metia un cero que no corresponde a ninguna zona.
        expect($sede->address)->toBeNull()
            ->and($sede->zone_id)->toBeNull()
            ->and($sede->opens_at)->toBeNull()
            ->and($sede->active_from)->toBeNull();
    });
})->group('directory');

it('exige codigo y nombre', function (): void {
    enTenant(function (): void {
        comoPropietario();

        Livewire::test(SiteForm::class)
            ->set('code', '')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['code' => 'required', 'name' => 'required']);
    });
})->group('directory');

it('no deja repetir el codigo de otra sede', function (): void {
    enTenant(function (): void {
        comoPropietario();
        Site::factory()->create(['code' => 'S-200']);

        Livewire::test(SiteForm::class)
            ->set('code', 'S-200')
            ->set('name', 'Otra')
            ->call('save')
            ->assertHasErrors(['code' => 'unique']);
    });
})->group('directory');

it('no cuenta el codigo propio como repetido al editar', function (): void {
    enTenant(function (): void {
        comoPropietario();
        $sede = Site::factory()->create(['code' => 'S-300', 'name' => 'Antigua']);

        Livewire::test(SiteForm::class, ['site' => $sede])
            ->set('name', 'Renombrada')
            ->call('save')
            ->assertHasNoErrors();

        expect($sede->refresh()->name)->toBe('Renombrada')
            ->and($sede->code)->toBe('S-300');
    });
})->group('directory');

it('rechaza una zona horaria inventada y coordenadas fuera de rango', function (): void {
    enTenant(function (): void {
        comoPropietario();

        Livewire::test(SiteForm::class)
            ->set('code', 'S-400')
            ->set('name', 'Sede')
            ->set('timezone', 'America/Narnia')
            ->set('latitude', '120')
            ->set('longitude', '-300')
            ->call('save')
            ->assertHasErrors(['timezone', 'latitude', 'longitude']);
    });
})->group('directory');

it('no deja que la vigencia termine antes de empezar', function (): void {
    enTenant(function (): void {
        comoPropietario();

        Livewire::test(SiteForm::class)
            ->set('code', 'S-500')
            ->set('name', 'Sede')
            ->set('activeFrom', '2026-06-01')
            ->set('activeUntil', '2026-05-01')
            ->call('save')
            ->assertHasErrors(['activeUntil']);
    });
})->group('directory');

it('carga el formulario de edicion con los valores de la sede', function (): void {
    enTenant(function (): void {
        comoPropietario();
        $sede = Site::factory()->create([
            'code' => 'S-600',
            'name' => 'Sede Existente',
            'opens_at' => '07:15:00',
        ]);

        Livewire::test(SiteForm::class, ['site' => $sede])
            ->assertSet('code', 'S-600')
            ->assertSet('name', 'Sede Existente')
            // El input type=time espera HH:MM, no HH:MM:SS.
            ->assertSet('opensAt', '07:15');
    });
})->group('directory');

it('no deja crear sedes a quien solo puede verlas', function (): void {
    enTenant(function (): void {
        comoEncargado('encargado@acme.test');

        Livewire::test(SiteForm::class)->assertForbidden();
    });
})->group('directory');

it('no deja editar una sede a quien no administra sedes', function (): void {
    enTenant(function (): void {
        $sede = Site::factory()->create(['code' => 'S-700']);

        // La tiene asignada, asi que la ve; administrarla es otra cosa.
        comoEncargado('encargado2@acme.test', $sede);

        Livewire::test(SiteForm::class, ['site' => $sede])->assertForbidden();
    });
})->group('directory');
