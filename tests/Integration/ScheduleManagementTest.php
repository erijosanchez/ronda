<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Forms\Application\Actions\CreateTemplate;
use Ronda\Forms\Application\Actions\PublishTemplateVersion;
use Ronda\Forms\Application\Data\TemplateData;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\PermissionName;
use Ronda\Identity\Domain\RoleName;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Scheduling\Application\Actions\LaunchSchedule;
use Ronda\Scheduling\Application\Actions\UpdateSchedule;
use Ronda\Scheduling\Application\Data\ScheduleData;
use Ronda\Scheduling\Domain\Exceptions\InvalidSchedule;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Scheduling\Domain\Models\Schedule;
use Ronda\Scheduling\Domain\ScheduleScope;
use Ronda\Scheduling\Domain\States\Fulfilled;
use Ronda\Scheduling\Presentation\Livewire\ScheduleForm;
use Ronda\Scheduling\Presentation\Livewire\ScheduleList;

// La pantalla de programaciones y la replanificacion. RONDA-PLAN-MAESTRO.md
// sec. 9.1 y ADR 0008.
//
// Lo delicado no es el formulario: es que cambiar o pausar una programacion
// mueva lo que todavia es un plan sin tocar lo que ya es historia.
//
// El reloj se fija el miercoles 16 de septiembre de 2026 a las 15:00 UTC, las
// 10:00 en Lima.

const PROG_CLAVE = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-09-16 15:00:00');

    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: PROG_CLAVE,
    ));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();

    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function enAcmePantalla(Closure $fn): void
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;
    $tenant->run($fn);
}

function propietariaDeAcme(): User
{
    $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();
    auth()->login($owner);

    return $owner;
}

function arqueoPublicado(string $code = 'ARQUEO'): Template
{
    $plantilla = resolve(CreateTemplate::class)(new TemplateData(code: $code, name: 'Arqueo '.$code));

    resolve(PublishTemplateVersion::class)(
        $plantilla,
        FormSchema::fromArray([['key' => 'monto', 'type' => 'money', 'label' => 'Monto']]),
        User::query()->where('email', 'owner@acme.test')->firstOrFail(),
    );

    return $plantilla->refresh();
}

function lanzarDiaria(Template $plantilla, array $extra = []): Schedule
{
    return resolve(LaunchSchedule::class)(new ScheduleData(...[
        'templateId' => $plantilla->id,
        'name' => 'Arqueo diario',
        'scope' => ScheduleScope::AllSites,
        'rrule' => 'FREQ=DAILY',
        'windowStart' => '08:00',
        'windowEnd' => '18:00',
        'startsOn' => '2026-09-01',
        ...$extra,
    ]));
}

/**
 * @return list<string>
 */
function fechasDe(Schedule $programacion): array
{
    return Obligation::query()
        ->where('schedule_id', $programacion->id)
        ->oldest('occurrence_date')
        ->get()
        ->map(fn (Obligation $o): string => $o->occurrence_date->toDateString())
        ->all();
}

it('crea la programacion desde la pantalla y materializa en el acto', function (): void {
    enAcmePantalla(function (): void {
        propietariaDeAcme();
        Site::factory()->create();
        $plantilla = arqueoPublicado();

        Livewire::test(ScheduleForm::class)
            ->assertSet('startsOn', '2026-09-16')
            ->set('templateId', (string) $plantilla->id)
            ->set('name', 'Arqueo de apertura')
            ->set('windowStart', '08:00')
            ->set('windowEnd', '18:00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('schedules.index'));

        $programacion = Schedule::query()->where('name', 'Arqueo de apertura')->firstOrFail();

        // Hoy (abierta ahora mismo) y los catorce dias del horizonte. No hace
        // falta esperar a la siguiente pasada del job.
        expect($programacion->rrule)->toBe('FREQ=DAILY')
            ->and($programacion->active)->toBeTrue()
            ->and(fechasDe($programacion))->toHaveCount(15)
            ->and(fechasDe($programacion)[0])->toBe('2026-09-16');
    });
})->group('scheduling');

it('no crea obligaciones que nacerian ya vencidas', function (): void {
    // Programar a las 10:00 un arqueo que vence a las 09:00 no puede dejar a la
    // sede con un incumplimiento que nunca pudo evitar.
    enAcmePantalla(function (): void {
        Site::factory()->create();

        $programacion = lanzarDiaria(arqueoPublicado(), ['windowStart' => '07:00', 'windowEnd' => '09:00']);

        expect(fechasDe($programacion))->not->toContain('2026-09-16')
            ->and(fechasDe($programacion))->not->toContain('2026-09-15')
            ->and(fechasDe($programacion)[0])->toBe('2026-09-17');
    });
})->group('scheduling');

it('guarda como RRULE la repeticion elegida con controles', function (): void {
    enAcmePantalla(function (): void {
        propietariaDeAcme();
        $plantilla = arqueoPublicado();

        Livewire::test(ScheduleForm::class)
            ->set('templateId', (string) $plantilla->id)
            ->set('name', 'Inventario')
            ->set('frequency', 'weekly')
            ->set('weekdays', ['FR', 'MO'])
            ->set('interval', '2')
            ->call('save')
            ->assertHasNoErrors();

        expect(Schedule::query()->where('name', 'Inventario')->value('rrule'))
            ->toBe('FREQ=WEEKLY;BYDAY=MO,FR;INTERVAL=2');
    });
})->group('scheduling');

it('muestra las proximas fechas mientras se edita la regla', function (): void {
    enAcmePantalla(function (): void {
        propietariaDeAcme();

        // Fin de mes desde hoy: septiembre, octubre y noviembre, cada uno con
        // su ultimo dia.
        Livewire::test(ScheduleForm::class)
            ->set('frequency', 'monthly')
            ->set('monthDay', '-1')
            ->assertViewHas('preview', fn (?array $fechas): bool => array_slice($fechas ?? [], 0, 3) === ['2026-09-30', '2026-10-31', '2026-11-30']);
    });
})->group('scheduling');

it('abre una programacion con los mismos controles con que se creo', function (): void {
    enAcmePantalla(function (): void {
        propietariaDeAcme();
        $programacion = lanzarDiaria(arqueoPublicado(), [
            'rrule' => 'FREQ=WEEKLY;BYDAY=TU,TH',
            'windowStart' => '09:30',
            'toleranceMinutes' => 15,
        ]);

        Livewire::test(ScheduleForm::class, ['schedule' => $programacion])
            ->assertSet('frequency', 'weekly')
            ->assertSet('weekdays', ['TU', 'TH'])
            ->assertSet('windowStart', '09:30')
            ->assertSet('toleranceMinutes', '15')
            ->assertSet('templateId', (string) $programacion->template_id);
    });
})->group('scheduling');

it('al cambiar la programacion replanifica lo futuro y respeta lo abierto y lo cumplido', function (): void {
    enAcmePantalla(function (): void {
        Site::factory()->create();
        $programacion = lanzarDiaria(arqueoPublicado());

        // Una entrega de manana ya cumplida (por ejemplo, adelantada). Lo
        // cumplido es historia: no puede desaparecer por editar.
        Obligation::query()
            ->where('schedule_id', $programacion->id)
            ->whereDate('occurrence_date', '2026-09-17')
            ->update(['status' => Fulfilled::$name]);

        // De diaria a solo los lunes.
        resolve(UpdateSchedule::class)($programacion, new ScheduleData(
            templateId: $programacion->template_id,
            name: 'Arqueo de los lunes',
            scope: ScheduleScope::AllSites,
            rrule: 'FREQ=WEEKLY;BYDAY=MO',
            windowStart: '08:00',
            windowEnd: '18:00',
            startsOn: '2026-09-01',
        ));

        // Hoy sigue (esta abierta), el 17 sigue (cumplida) y del resto solo
        // quedan los lunes 21 y 28.
        expect(fechasDe($programacion))->toBe(['2026-09-16', '2026-09-17', '2026-09-21', '2026-09-28']);
    });
})->group('scheduling');

it('al reducir las sedes retira lo que no abrio de las que salen', function (): void {
    enAcmePantalla(function (): void {
        $queda = Site::factory()->create();
        $sale = Site::factory()->create();
        $programacion = lanzarDiaria(arqueoPublicado(), [
            'scope' => ScheduleScope::Sites,
            'siteIds' => [$queda->id, $sale->id],
        ]);

        resolve(UpdateSchedule::class)($programacion, new ScheduleData(
            templateId: $programacion->template_id,
            name: $programacion->name,
            scope: ScheduleScope::Sites,
            rrule: 'FREQ=DAILY',
            windowStart: '08:00',
            windowEnd: '18:00',
            startsOn: '2026-09-01',
            siteIds: [$queda->id],
        ));

        $deLaQueSale = Obligation::query()->where('site_id', $sale->id)->get();

        // Solo le queda la de hoy, que ya esta abierta.
        expect($deLaQueSale)->toHaveCount(1)
            ->and($deLaQueSale->first()?->occurrence_date->toDateString())->toBe('2026-09-16')
            ->and(Obligation::query()->where('site_id', $queda->id)->count())->toBe(15);
    });
})->group('scheduling');

it('no deja cambiar la plantilla de una programacion', function (): void {
    enAcmePantalla(function (): void {
        $programacion = lanzarDiaria(arqueoPublicado());
        $otra = arqueoPublicado('OTRA');

        expect(fn () => resolve(UpdateSchedule::class)($programacion, new ScheduleData(
            templateId: $otra->id,
            name: $programacion->name,
            scope: ScheduleScope::AllSites,
            rrule: 'FREQ=DAILY',
            windowStart: '08:00',
            windowEnd: '18:00',
            startsOn: '2026-09-01',
        )))->toThrow(InvalidSchedule::class);
    });
})->group('scheduling');

it('pausar retira lo que no abrio y reanudar lo vuelve a pedir', function (): void {
    enAcmePantalla(function (): void {
        propietariaDeAcme();
        Site::factory()->create();
        $programacion = lanzarDiaria(arqueoPublicado());

        Livewire::test(ScheduleList::class)->call('pause', $programacion->id)->assertHasNoErrors();

        expect($programacion->refresh()->active)->toBeFalse()
            ->and(fechasDe($programacion))->toBe(['2026-09-16']);

        Livewire::test(ScheduleList::class)->call('resume', $programacion->id)->assertHasNoErrors();

        expect($programacion->refresh()->active)->toBeTrue()
            ->and(fechasDe($programacion))->toHaveCount(15);
    });
})->group('scheduling');

it('valida la repeticion, la ventana y el alcance', function (): void {
    enAcmePantalla(function (): void {
        propietariaDeAcme();
        $plantilla = arqueoPublicado();

        Livewire::test(ScheduleForm::class)
            ->set('templateId', (string) $plantilla->id)
            ->set('name', 'Mal armada')
            ->set('frequency', 'weekly')
            ->set('weekdays', [])
            ->set('windowStart', '18:00')
            ->set('windowEnd', '08:00')
            ->set('scope', 'sites')
            ->set('siteIds', [])
            ->call('save')
            ->assertHasErrors(['weekdays', 'windowEnd', 'siteIds']);

        expect(Schedule::query()->count())->toBe(0);
    });
})->group('scheduling');

it('muestra el error de una regla personalizada invalida en vez de reventar', function (): void {
    enAcmePantalla(function (): void {
        propietariaDeAcme();
        $plantilla = arqueoPublicado();

        Livewire::test(ScheduleForm::class)
            ->set('templateId', (string) $plantilla->id)
            ->set('name', 'Cada hora')
            ->set('frequency', 'custom')
            ->set('customRule', 'FREQ=HOURLY')
            ->call('save')
            ->assertHasErrors(['schedule']);

        expect(Schedule::query()->count())->toBe(0);
    });
})->group('scheduling');

it('no deja programar una plantilla sin publicar desde la pantalla', function (): void {
    enAcmePantalla(function (): void {
        propietariaDeAcme();
        $borrador = resolve(CreateTemplate::class)(new TemplateData(code: 'SIN', name: 'Sin publicar'));

        Livewire::test(ScheduleForm::class)
            ->set('templateId', (string) $borrador->id)
            ->set('name', 'Imposible')
            ->call('save')
            ->assertHasErrors(['templateId']);
    });
})->group('scheduling');

it('no deja programar sedes que el usuario no alcanza', function (): void {
    enAcmePantalla(function (): void {
        $suya = Site::factory()->create();
        $ajena = Site::factory()->create();
        $plantilla = arqueoPublicado();

        // Programa, pero no administra sedes: solo alcanza la suya.
        $coordinador = User::create(['name' => 'Coordinador', 'email' => 'coord@acme.test', 'password' => PROG_CLAVE]);
        $coordinador->givePermissionTo([PermissionName::ScheduleView->value, PermissionName::ScheduleManage->value, PermissionName::SiteView->value]);
        $coordinador->sites()->attach($suya->id);
        auth()->login($coordinador->fresh());

        Livewire::test(ScheduleForm::class)
            ->set('templateId', (string) $plantilla->id)
            ->set('name', 'Colada')
            ->set('scope', 'sites')
            ->set('siteIds', [(string) $suya->id, (string) $ajena->id])
            ->call('save')
            ->assertHasErrors(['siteIds']);

        expect(Schedule::query()->count())->toBe(0);
    });
})->group('scheduling');

it('deja ver las programaciones a un encargado pero no cambiarlas', function (): void {
    enAcmePantalla(function (): void {
        $programacion = lanzarDiaria(arqueoPublicado());

        $encargado = User::create(['name' => 'Encargado', 'email' => 'enc@acme.test', 'password' => PROG_CLAVE]);
        $encargado->assignRole(RoleName::SiteManager->value);
        auth()->login($encargado->fresh());

        Livewire::test(ScheduleList::class)
            ->assertOk()
            ->assertViewHas('canManage', false);

        Livewire::test(ScheduleForm::class)->assertForbidden();
        Livewire::test(ScheduleForm::class, ['schedule' => $programacion])->assertForbidden();
        Livewire::test(ScheduleList::class)->call('pause', $programacion->id)->assertForbidden();

        expect($programacion->refresh()->active)->toBeTrue();
    });
})->group('scheduling');

it('sirve el listado, el alta y la edicion dentro del layout', function (): void {
    enAcmePantalla(function (): void {
        $programacion = lanzarDiaria(arqueoPublicado(), ['rrule' => 'FREQ=MONTHLY;BYMONTHDAY=-1']);
        test()->programacionId = $programacion->id;
    });

    $url = 'http://acme.ronda.test';

    $this->post($url.'/login', ['email' => 'owner@acme.test', 'password' => PROG_CLAVE]);

    $this->get($url.'/programaciones')
        ->assertOk()
        ->assertSee('Arqueo diario')
        ->assertSee('El último día de cada mes');

    $this->get($url.'/programaciones/nueva')->assertOk()->assertSee('Próximas fechas');

    $this->get($url.'/programaciones/'.$this->programacionId.'/editar')
        ->assertOk()
        ->assertSee('Arqueo diario');
})->group('scheduling');
