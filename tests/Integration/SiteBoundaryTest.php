<?php

declare(strict_types=1);

use Ronda\Directory\Domain\Models\Site;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\RoleName;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;

// Frontera por sede. RONDA-PLAN-MAESTRO.md sec. 10.3
//
// El plan exige DOS capas: un Global Scope que filtra la consulta y una Policy
// que vuelve a comprobar sobre el registro. Aqui se prueban por separado,
// porque el sentido de la segunda es justamente sobrevivir a que la primera se
// desactive.

const CLAVE_SEDES = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_SEDES,
    ));

    $this->url = 'http://acme.ronda.test';
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function enAcme(Closure $fn): mixed
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;
    $resultado = null;

    $tenant->run(function () use ($fn, &$resultado): void {
        $resultado = $fn();
    });

    return $resultado;
}

/**
 * Crea tres sedes y devuelve la coleccion. Sin sesion activa el scope no
 * filtra, asi que se ven todas.
 *
 * @return array<int, Site>
 */
function tresSedes(): array
{
    return Site::factory()->count(3)->create()->all();
}

function usuarioDeSede(RoleName $rol, ?Site $sede = null): User
{
    $user = User::create([
        'name' => 'Persona '.$rol->value,
        'email' => $rol->value.'@acme.test',
        'password' => CLAVE_SEDES,
    ]);
    $user->assignRole($rol->value);

    if ($sede instanceof Site) {
        $user->sites()->attach($sede->getKey());
    }

    return $user->fresh();
}

it('deja ver solo las sedes asignadas', function (): void {
    enAcme(function (): void {
        [$suya] = tresSedes();
        $encargado = usuarioDeSede(RoleName::SiteManager, $suya);

        auth()->login($encargado);

        $vistas = Site::query()->pluck('code')->all();

        expect($vistas)->toBe([$suya->code]);
    });
})->group('tenancy');

it('deja ver todas a quien administra sedes', function (): void {
    enAcme(function (): void {
        tresSedes();
        $admin = usuarioDeSede(RoleName::Admin);

        auth()->login($admin);

        // Tiene site.manage: ve el parque entero, incluidas las que aun no ha
        // asignado a nadie.
        expect(Site::query()->count())->toBe(3);
    });
})->group('tenancy');

it('deja ver todas al propietario', function (): void {
    enAcme(function (): void {
        tresSedes();
        auth()->login(User::query()->where('email', 'owner@acme.test')->firstOrFail());

        expect(Site::query()->count())->toBe(3);
    });
})->group('tenancy');

it('no filtra cuando no hay sesion, para consola y jobs', function (): void {
    enAcme(function (): void {
        tresSedes();
        auth()->logout();

        expect(Site::query()->count())->toBe(3);
    });
})->group('tenancy');

it('la Policy sigue negando aunque se desactive el scope', function (): void {
    // Esta es la razon de que haya dos capas. Un `withoutGlobalScopes()` puesto
    // para otra cosa deja pasar la consulta; la Policy tiene que frenar igual.
    enAcme(function (): void {
        [$suya, $ajena] = tresSedes();
        $encargado = usuarioDeSede(RoleName::SiteManager, $suya);

        auth()->login($encargado);

        $sinScope = Site::query()->withoutGlobalScopes()->findOrFail($ajena->getKey());

        expect($encargado->can('view', $sinScope))->toBeFalse()
            ->and($encargado->can('view', $suya))->toBeTrue();
    });
})->group('tenancy');

it('no deja administrar sedes a quien solo puede verlas', function (): void {
    enAcme(function (): void {
        [$suya] = tresSedes();
        $encargado = usuarioDeSede(RoleName::SiteManager, $suya);

        expect($encargado->can('create', Site::class))->toBeFalse()
            ->and($encargado->can('update', $suya))->toBeFalse()
            ->and($encargado->can('delete', $suya))->toBeFalse();
    });
})->group('tenancy');

it('el listado solo muestra las sedes del usuario', function (): void {
    /** @var array{0: Site, 1: Site} $sedes */
    $sedes = enAcme(function (): array {
        [$suya, $ajena] = tresSedes();
        usuarioDeSede(RoleName::SiteManager, $suya);

        return [$suya, $ajena];
    });

    [$suya, $ajena] = $sedes;

    $this->post($this->url.'/login', [
        'email' => RoleName::SiteManager->value.'@acme.test',
        'password' => CLAVE_SEDES,
    ]);

    $this->get($this->url.'/sedes')
        ->assertOk()
        ->assertSee($suya->code)
        ->assertDontSee($ajena->code);
})->group('tenancy');

it('excluye las sedes cerradas del filtro de activas', function (): void {
    enAcme(function (): void {
        Site::factory()->create(['code' => 'ABIERTA']);
        Site::factory()->closed()->create(['code' => 'CERRADA']);

        $activas = Site::query()->active()->pluck('code')->all();

        expect($activas)->toBe(['ABIERTA']);
    });
})->group('tenancy');
