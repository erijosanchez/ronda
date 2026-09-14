<?php

declare(strict_types=1);

use Livewire\Livewire;
use Ronda\Directory\Domain\Models\Position;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Directory\Presentation\Livewire\UserSiteAssignments;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\PermissionName;
use Ronda\Identity\Domain\RoleName;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;

// Asignacion de personas a sedes. RONDA-PLAN-MAESTRO.md sec. 8.3 y 10.3
//
// Es la pantalla que da sentido a la frontera por sede: hasta que alguien
// asigna, un encargado no ve ninguna sede.

const CLAVE_ASIG = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_ASIG,
    ));
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function enAcmeAsig(Closure $fn): void
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;
    $tenant->run($fn);
}

function propietariaAsig(): User
{
    $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();
    auth()->login($owner);

    return $owner;
}

function encargadaSinSedes(string $email = 'encargada@acme.test'): User
{
    $user = User::create([
        'name' => 'Encargada',
        'email' => $email,
        'password' => CLAVE_ASIG,
    ]);
    $user->assignRole(RoleName::SiteManager->value);

    return $user->fresh();
}

it('guarda las sedes elegidas con su cargo y su rol', function (): void {
    enAcmeAsig(function (): void {
        propietariaAsig();
        $encargada = encargadaSinSedes();
        [$una, $otra] = Site::factory()->count(2)->create()->all();
        // La provision ya siembra el catalogo de cargos: crear uno con el
        // mismo nombre choca con la restriccion de unicidad.
        $cargo = Position::query()->where('name', 'Encargado de sede')->firstOrFail();

        Livewire::test(UserSiteAssignments::class, ['user' => $encargada])
            ->call('toggle', $una->getKey())
            ->set("assignments.{$una->getKey()}.position_id", (string) $cargo->getKey())
            ->set("assignments.{$una->getKey()}.role", RoleName::SiteManager->value)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('users.index'));

        $asignadas = $encargada->fresh()->sites;

        expect($asignadas->pluck('id')->all())->toBe([$una->getKey()])
            ->and($asignadas->first()->pivot->position_id)->toBe($cargo->getKey())
            ->and($asignadas->first()->pivot->role)->toBe(RoleName::SiteManager->value)
            ->and($asignadas->pluck('id'))->not->toContain($otra->getKey());
    });
})->group('directory');

it('hace que la persona empiece a ver esa sede y solo esa', function (): void {
    // El ciclo entero: antes de asignar no ve nada; despues ve una.
    enAcmeAsig(function (): void {
        propietariaAsig();
        $encargada = encargadaSinSedes();
        [$suya] = Site::factory()->count(3)->create()->all();

        auth()->login($encargada);
        expect(Site::query()->count())->toBe(0);

        propietariaAsig();
        Livewire::test(UserSiteAssignments::class, ['user' => $encargada])
            ->call('toggle', $suya->getKey())
            ->call('save')
            ->assertHasNoErrors();

        auth()->login($encargada->fresh());
        expect(Site::query()->pluck('code')->all())->toBe([$suya->code]);
    });
})->group('directory');

it('sustituye la lista entera al guardar', function (): void {
    enAcmeAsig(function (): void {
        propietariaAsig();
        $encargada = encargadaSinSedes();
        [$vieja, $nueva] = Site::factory()->count(2)->create()->all();

        $encargada->sites()->attach($vieja->getKey());

        Livewire::test(UserSiteAssignments::class, ['user' => $encargada])
            // Quitar la que tenia y poner otra.
            ->call('toggle', $vieja->getKey())
            ->call('toggle', $nueva->getKey())
            ->call('save')
            ->assertHasNoErrors();

        expect($encargada->fresh()->sites->pluck('id')->all())->toBe([$nueva->getKey()]);
    });
})->group('directory');

it('carga las asignaciones que ya tenia', function (): void {
    enAcmeAsig(function (): void {
        propietariaAsig();
        $encargada = encargadaSinSedes();
        $sede = Site::factory()->create();
        $cargo = Position::query()->where('name', 'Supervisor')->firstOrFail();

        $encargada->sites()->attach($sede->getKey(), [
            'position_id' => $cargo->getKey(),
            'role' => RoleName::Supervisor->value,
        ]);

        Livewire::test(UserSiteAssignments::class, ['user' => $encargada->fresh()])
            ->assertSet("assignments.{$sede->getKey()}.position_id", (string) $cargo->getKey())
            ->assertSet("assignments.{$sede->getKey()}.role", RoleName::Supervisor->value);
    });
})->group('directory');

it('rechaza un cargo o un rol que no existen', function (): void {
    // El estado de un componente Livewire viaja por el cliente: hay que
    // validarlo aunque las opciones salgan de la propia consulta.
    enAcmeAsig(function (): void {
        propietariaAsig();
        $encargada = encargadaSinSedes();
        $sede = Site::factory()->create();

        Livewire::test(UserSiteAssignments::class, ['user' => $encargada])
            ->call('toggle', $sede->getKey())
            ->set("assignments.{$sede->getKey()}.position_id", '999999')
            ->set("assignments.{$sede->getKey()}.role", 'emperador')
            ->call('save')
            ->assertHasErrors([
                "assignments.{$sede->getKey()}.position_id",
                "assignments.{$sede->getKey()}.role",
            ]);
    });
})->group('directory');

it('conserva lo marcado al cambiar de pagina', function (): void {
    // El listado pagina (regla 5), pero lo elegido vive en el componente y no
    // en la pagina: si se perdiese, asignar un parque grande seria imposible.
    enAcmeAsig(function (): void {
        propietariaAsig();
        $encargada = encargadaSinSedes();
        $sedes = Site::factory()->count(20)->create();
        $primera = $sedes->sortBy('name')->first();

        Livewire::test(UserSiteAssignments::class, ['user' => $encargada])
            ->call('toggle', $primera->getKey())
            ->call('nextPage')
            ->assertSet("assignments.{$primera->getKey()}.position_id", null)
            ->call('save')
            ->assertHasNoErrors();

        expect($encargada->fresh()->sites->pluck('id')->all())->toBe([$primera->getKey()]);
    });
})->group('directory');

it('no deja asignar sedes a quien no gestiona usuarios', function (): void {
    enAcmeAsig(function (): void {
        $encargada = encargadaSinSedes();
        $otra = encargadaSinSedes('otra@acme.test');

        auth()->login($encargada);

        Livewire::test(UserSiteAssignments::class, ['user' => $otra])->assertForbidden();
    });
})->group('directory');

it('solo ofrece las sedes que el propio asignador alcanza', function (): void {
    enAcmeAsig(function (): void {
        $sedes = Site::factory()->count(3)->create();
        $suya = $sedes->first();

        // Con los roles predefinidos este limite no llega a notarse: los dos
        // que traen `user.manage` (owner y admin) traen tambien `site.manage`,
        // asi que ven el parque entero.
        //
        // Se prueba con permisos a medida porque el plan (sec. 10.3) dice que
        // el cliente clona y ajusta los roles: en cuanto alguien cree uno que
        // gestione personas sin administrar sedes, este limite es el que evita
        // que reparta acceso a sedes que el mismo no alcanza.
        $jefa = User::create([
            'name' => 'Jefa de personal',
            'email' => 'personal@acme.test',
            'password' => CLAVE_ASIG,
        ]);
        $jefa->givePermissionTo([
            PermissionName::UserView->value,
            PermissionName::UserManage->value,
            PermissionName::SiteView->value,
        ]);
        $jefa->sites()->attach($suya->getKey());

        auth()->login($jefa->fresh());

        $encargada = encargadaSinSedes();

        Livewire::test(UserSiteAssignments::class, ['user' => $encargada])
            ->assertOk()
            ->assertSee($suya->code)
            ->assertDontSee($sedes->get(1)->code);
    });
})->group('directory');
