<?php

declare(strict_types=1);

use Livewire\Livewire;
use Ronda\Directory\Application\Actions\DeletePosition;
use Ronda\Directory\Application\Actions\DeleteZone;
use Ronda\Directory\Application\Actions\UpdateZone;
use Ronda\Directory\Application\Data\ZoneData;
use Ronda\Directory\Domain\Exceptions\CannotDeletePosition;
use Ronda\Directory\Domain\Exceptions\CannotDeleteZone;
use Ronda\Directory\Domain\Models\Position;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Directory\Domain\Models\Zone;
use Ronda\Directory\Presentation\Livewire\PositionList;
use Ronda\Directory\Presentation\Livewire\ZoneForm;
use Ronda\Directory\Presentation\Livewire\ZoneList;
use Ronda\Forms\Application\Actions\CreateTemplate;
use Ronda\Forms\Application\Actions\PublishTemplateVersion;
use Ronda\Forms\Application\Data\TemplateData;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\RoleName;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Scheduling\Application\Actions\CreateSchedule;
use Ronda\Scheduling\Application\Data\ScheduleData;
use Ronda\Scheduling\Domain\ScheduleScope;

// Zonas y cargos: la estructura del cliente. RONDA-PLAN-MAESTRO.md sec. 8.3
//
// Hasta ahora solo se podian sembrar por codigo. Lo que importa aqui es que
// borrar no deje huerfano nada que dependa de ellos, y que la jerarquia de
// zonas no pueda formar un ciclo.

const CLAVE_ESTRUCTURA = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_ESTRUCTURA,
    ));
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function enAcmeEstructura(Closure $fn): mixed
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;

    return $tenant->run($fn);
}

function comoDuenaDeAcme(): User
{
    $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();
    auth()->login($owner);

    return $owner;
}

it('crea una zona desde la pantalla y la deja lista para las sedes', function (): void {
    enAcmeEstructura(function (): void {
        comoDuenaDeAcme();

        Livewire::test(ZoneForm::class)
            ->set('name', 'Lima Norte')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('zones.index'));

        $zona = Zone::query()->where('name', 'Lima Norte')->firstOrFail();

        expect($zona->parent_id)->toBeNull();

        // Y ya se puede colgar una sede de ella.
        $sede = Site::factory()->create(['zone_id' => $zona->id]);

        expect($sede->zone?->name)->toBe('Lima Norte');
    });
})->group('directory');

it('no admite dos zonas con el mismo nombre', function (): void {
    enAcmeEstructura(function (): void {
        comoDuenaDeAcme();
        Zone::create(['name' => 'Centro']);

        Livewire::test(ZoneForm::class)
            ->set('name', 'Centro')
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);
    });
})->group('directory');

it('anida zonas pero no deja formar un ciclo', function (): void {
    enAcmeEstructura(function (): void {
        comoDuenaDeAcme();
        $pais = Zone::create(['name' => 'Peru']);
        $region = Zone::create(['name' => 'Lima', 'parent_id' => $pais->id]);
        $distrito = Zone::create(['name' => 'Miraflores', 'parent_id' => $region->id]);

        $actualizar = resolve(UpdateZone::class);

        // Colgar el pais de su propio distrito seria un ciclo: recorrer el
        // arbol no terminaria nunca.
        expect(fn () => $actualizar($pais, new ZoneData(name: 'Peru', parentId: $distrito->id)))
            ->toThrow(CannotDeleteZone::class)
            // Ni de si misma.
            ->and(fn () => $actualizar($pais, new ZoneData(name: 'Peru', parentId: $pais->id)))
            ->toThrow(CannotDeleteZone::class);

        // Lo que si vale: mover el distrito al primer nivel.
        $actualizar($distrito, new ZoneData(name: 'Miraflores'));

        expect($distrito->refresh()->parent_id)->toBeNull();
    });
})->group('directory');

it('no borra una zona con sedes, con subzonas o usada en una programacion', function (): void {
    enAcmeEstructura(function (): void {
        comoDuenaDeAcme();
        $borrar = resolve(DeleteZone::class);

        // Con una sede dentro.
        $conSedes = Zone::create(['name' => 'Con sedes']);
        Site::factory()->create(['zone_id' => $conSedes->id]);

        // Con una subzona.
        $madre = Zone::create(['name' => 'Madre']);
        Zone::create(['name' => 'Hija', 'parent_id' => $madre->id]);

        // Usada por una programacion: la base no lo impide (la zona se borra
        // logicamente), y la programacion se quedaria sin materializar en
        // silencio.
        $programada = Zone::create(['name' => 'Programada']);
        $plantilla = resolve(CreateTemplate::class)(new TemplateData(code: 'ARQ', name: 'Arqueo'));
        resolve(PublishTemplateVersion::class)($plantilla, FormSchema::fromArray([
            ['key' => 'monto', 'type' => 'money', 'label' => 'Monto'],
        ]), User::query()->where('email', 'owner@acme.test')->firstOrFail());
        resolve(CreateSchedule::class)(new ScheduleData(
            templateId: $plantilla->id,
            name: 'Por zona',
            scope: ScheduleScope::Zone,
            rrule: 'FREQ=DAILY',
            windowStart: '08:00',
            windowEnd: '18:00',
            startsOn: '2026-09-01',
            zoneId: $programada->id,
        ));

        expect(fn () => $borrar($conSedes))->toThrow(CannotDeleteZone::class, 'sede')
            ->and(fn () => $borrar($madre))->toThrow(CannotDeleteZone::class, 'zona')
            ->and(fn () => $borrar($programada))->toThrow(CannotDeleteZone::class, 'programacion');

        expect(Zone::query()->count())->toBe(4);
    });
})->group('directory');

it('borra una zona vacia y lo dice en pantalla cuando no puede', function (): void {
    enAcmeEstructura(function (): void {
        comoDuenaDeAcme();
        $vacia = Zone::create(['name' => 'Vacia']);
        $ocupada = Zone::create(['name' => 'Ocupada']);
        Site::factory()->create(['zone_id' => $ocupada->id]);

        Livewire::test(ZoneList::class)
            ->call('delete', $vacia->id)
            ->assertHasNoErrors()
            ->call('delete', $ocupada->id)
            ->assertHasErrors(['zone']);

        expect(Zone::query()->count())->toBe(1)
            ->and(Zone::query()->first()?->name)->toBe('Ocupada');
    });
})->group('directory');

it('crea, edita y borra cargos desde la misma pantalla', function (): void {
    enAcmeEstructura(function (): void {
        comoDuenaDeAcme();

        $pantalla = Livewire::test(PositionList::class)
            ->set('name', 'Jefe de turno')
            ->set('level', '25')
            ->call('save')
            ->assertHasNoErrors();

        $cargo = Position::query()->where('name', 'Jefe de turno')->firstOrFail();

        expect($cargo->level)->toBe(25);

        // Editar reutiliza el mismo formulario.
        $pantalla->call('edit', $cargo->id)
            ->assertSet('name', 'Jefe de turno')
            ->set('name', 'Jefe de turno noche')
            ->call('save')
            ->assertHasNoErrors()
            // Y al guardar, el formulario vuelve a estar en blanco.
            ->assertSet('editingId', null)
            ->assertSet('name', '');

        expect($cargo->refresh()->name)->toBe('Jefe de turno noche');

        $pantalla->call('delete', $cargo->id)->assertHasNoErrors();

        expect(Position::query()->whereKey($cargo->id)->exists())->toBeFalse();
    });
})->group('directory');

it('no borra un cargo que alguien ocupa en una sede', function (): void {
    enAcmeEstructura(function (): void {
        comoDuenaDeAcme();
        $sede = Site::factory()->create();
        $cargo = Position::create(['name' => 'Encargada', 'level' => 10]);

        $persona = User::create(['name' => 'Ana', 'email' => 'ana@acme.test', 'password' => CLAVE_ESTRUCTURA]);
        $persona->sites()->attach($sede->id, ['position_id' => $cargo->id]);

        expect(fn () => resolve(DeletePosition::class)($cargo))->toThrow(CannotDeletePosition::class);

        Livewire::test(PositionList::class)
            ->call('delete', $cargo->id)
            ->assertHasErrors(['position']);

        expect(Position::query()->whereKey($cargo->id)->exists())->toBeTrue();
    });
})->group('directory');

it('no deja tocar la estructura a quien solo mira', function (): void {
    enAcmeEstructura(function (): void {
        $zona = Zone::create(['name' => 'Norte']);
        $cargo = Position::create(['name' => 'Encargada', 'level' => 10]);

        // Un encargado de sede ve sedes, pero no administra estructura.
        $encargado = User::create(['name' => 'Beto', 'email' => 'beto@acme.test', 'password' => CLAVE_ESTRUCTURA]);
        $encargado->assignRole(RoleName::SiteManager->value);
        auth()->login($encargado->fresh());

        Livewire::test(ZoneList::class)
            ->assertOk()
            ->assertViewHas('canManage', false)
            ->call('delete', $zona->id)
            ->assertForbidden();

        Livewire::test(ZoneForm::class)->assertForbidden();

        // Los cargos ni los ve: son parte de administrar personas.
        Livewire::test(PositionList::class)->assertForbidden();

        expect(Zone::query()->count())->toBe(1)
            ->and(Position::query()->count())->toBeGreaterThan(0)
            ->and($cargo->refresh()->exists)->toBeTrue();
    });
})->group('directory');

it('sirve las dos pantallas dentro del layout', function (): void {
    enAcmeEstructura(function (): void {
        Zone::create(['name' => 'Lima Norte']);
    });

    $url = 'http://acme.ronda.test';
    $this->post($url.'/login', ['email' => 'owner@acme.test', 'password' => CLAVE_ESTRUCTURA]);

    $this->get($url.'/zonas')->assertOk()->assertSee('Lima Norte')->assertSee('Zonas');
    $this->get($url.'/zonas/nueva')->assertOk()->assertSee('Nueva zona');
    // Los cargos vienen sembrados en la provision del tenant.
    $this->get($url.'/cargos')->assertOk()->assertSee('Supervisor');
})->group('directory');
