<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Directory\Domain\Models\Zone;
use Ronda\Forms\Application\Actions\CreateTemplate;
use Ronda\Forms\Application\Actions\PublishTemplateVersion;
use Ronda\Forms\Application\Data\TemplateData;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Identity\Domain\Models\User;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Scheduling\Application\Actions\CreateSchedule;
use Ronda\Scheduling\Application\Actions\MarkMissedObligations;
use Ronda\Scheduling\Application\Actions\MaterializeObligations;
use Ronda\Scheduling\Application\Data\ScheduleData;
use Ronda\Scheduling\Domain\Exceptions\InvalidRecurrence;
use Ronda\Scheduling\Domain\Exceptions\InvalidSchedule;
use Ronda\Scheduling\Domain\Models\Holiday;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Scheduling\Domain\Models\Schedule;
use Ronda\Scheduling\Domain\ScheduleScope;
use Ronda\Scheduling\Domain\States\Excused;
use Ronda\Scheduling\Domain\States\Fulfilled;
use Ronda\Scheduling\Domain\States\Missed;
use Ronda\Scheduling\Domain\States\Pending;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

// Materializacion de obligaciones contra PostgreSQL. ADR 0008.
//
// La logica de calendario ya se prueba en Unit/SchedulingDomainTest. Aqui se
// prueba lo que solo existe con base de datos: la idempotencia, las
// restricciones, el alcance por zona y la maquina de estados.

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

function enAcmeProg(Closure $fn): void
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;
    $tenant->run($fn);
}

function plantillaPublicada(): Template
{
    $plantilla = resolve(CreateTemplate::class)(new TemplateData(code: 'ARQUEO', name: 'Arqueo'));

    resolve(PublishTemplateVersion::class)(
        $plantilla,
        FormSchema::fromArray([['key' => 'monto', 'type' => 'money', 'label' => 'Monto']]),
        User::query()->where('email', 'owner@acme.test')->firstOrFail(),
    );

    return $plantilla->refresh();
}

function programacionDiaria(Template $plantilla, array $extra = []): Schedule
{
    return resolve(CreateSchedule::class)(new ScheduleData(...[
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

it('materializa una obligacion por sede y por dia', function (): void {
    enAcmeProg(function (): void {
        Site::factory()->count(2)->create();
        $programacion = programacionDiaria(plantillaPublicada());

        $creadas = resolve(MaterializeObligations::class)($programacion, '2026-09-15', '2026-09-17');

        expect($creadas)->toBe(6)
            ->and(Obligation::query()->count())->toBe(6)
            ->and(Obligation::query()->first()?->status)->toBeInstanceOf(Pending::class);
    });
})->group('scheduling');

it('es idempotente: reejecutarla no duplica nada', function (): void {
    // Corre cada hora sobre un horizonte que se solapa. Sin esto el parque se
    // llena de obligaciones repetidas y el KPI deja de significar nada.
    enAcmeProg(function (): void {
        Site::factory()->count(2)->create();
        $programacion = programacionDiaria(plantillaPublicada());
        $materializar = resolve(MaterializeObligations::class);

        $materializar($programacion, '2026-09-15', '2026-09-17');
        $segunda = $materializar($programacion, '2026-09-15', '2026-09-17');
        // Solapada con un dia nuevo.
        $tercera = $materializar($programacion, '2026-09-16', '2026-09-18');

        expect($segunda)->toBe(0)
            ->and($tercera)->toBe(2)
            ->and(Obligation::query()->count())->toBe(8);
    });
})->group('scheduling');

it('no mueve obligaciones ya creadas aunque cambie la programacion', function (): void {
    enAcmeProg(function (): void {
        Site::factory()->create();
        $programacion = programacionDiaria(plantillaPublicada());
        $materializar = resolve(MaterializeObligations::class);

        $materializar($programacion, '2026-09-15', '2026-09-15');
        $original = Obligation::query()->firstOrFail()->due_at->toIso8601String();

        // Se cambia la ventana y se vuelve a materializar el mismo dia.
        $programacion->update(['window_end' => '12:00']);
        $materializar($programacion->refresh(), '2026-09-15', '2026-09-15');

        // Es una foto: conserva su vencimiento original.
        expect(Obligation::query()->firstOrFail()->due_at->toIso8601String())->toBe($original);
    });
})->group('scheduling');

it('salta los feriados sembrados en la provision', function (): void {
    enAcmeProg(function (): void {
        Site::factory()->create();
        $programacion = programacionDiaria(plantillaPublicada(), ['startsOn' => '2026-07-01']);

        resolve(MaterializeObligations::class)($programacion, '2026-07-27', '2026-07-30');

        $fechas = Obligation::query()->oldest('occurrence_date')->pluck('occurrence_date')
            ->map(fn ($d): string => $d->toDateString())->all();

        // 28 y 29 de julio son Fiestas Patrias.
        expect($fechas)->toBe(['2026-07-27', '2026-07-30']);
    });
})->group('scheduling');

it('siembra los feriados de un ano que aun no los tenia', function (): void {
    enAcmeProg(function (): void {
        Site::factory()->create();
        $programacion = programacionDiaria(plantillaPublicada(), ['startsOn' => '2030-01-01']);

        expect(Holiday::query()->whereYear('date', 2030)->exists())->toBeFalse();

        resolve(MaterializeObligations::class)($programacion, '2030-12-24', '2030-12-26');

        expect(Holiday::query()->whereYear('date', 2030)->exists())->toBeTrue()
            ->and(Obligation::query()->whereDate('occurrence_date', '2030-12-25')->exists())->toBeFalse();
    });
})->group('scheduling');

it('limita el alcance por zona a las sedes de esa zona', function (): void {
    enAcmeProg(function (): void {
        $norte = Zone::create(['name' => 'Norte']);
        $sur = Zone::create(['name' => 'Sur']);
        $delNorte = Site::factory()->create(['zone_id' => $norte->id]);
        Site::factory()->create(['zone_id' => $sur->id]);

        $programacion = programacionDiaria(plantillaPublicada(), [
            'scope' => ScheduleScope::Zone,
            'zoneId' => $norte->id,
        ]);

        resolve(MaterializeObligations::class)($programacion, '2026-09-15', '2026-09-15');

        expect(Obligation::query()->pluck('site_id')->all())->toBe([$delNorte->id]);
    });
})->group('scheduling');

it('limita el alcance por lista a las sedes elegidas', function (): void {
    enAcmeProg(function (): void {
        [$una, , $otra] = Site::factory()->count(3)->create()->all();

        $programacion = programacionDiaria(plantillaPublicada(), [
            'scope' => ScheduleScope::Sites,
            'siteIds' => [$una->id, $otra->id],
        ]);

        resolve(MaterializeObligations::class)($programacion, '2026-09-15', '2026-09-15');

        expect(Obligation::query()->orderBy('site_id')->pluck('site_id')->all())->toBe([$una->id, $otra->id]);
    });
})->group('scheduling');

it('no materializa nada para una programacion inactiva', function (): void {
    enAcmeProg(function (): void {
        Site::factory()->create();
        $programacion = programacionDiaria(plantillaPublicada());
        $programacion->update(['active' => false]);

        expect(resolve(MaterializeObligations::class)($programacion->refresh(), '2026-09-15', '2026-09-20'))->toBe(0);
    });
})->group('scheduling');

it('no deja programar una plantilla sin publicar', function (): void {
    enAcmeProg(function (): void {
        $borrador = resolve(CreateTemplate::class)(new TemplateData(code: 'NUEVA', name: 'Nueva'));

        expect(fn (): Schedule => programacionDiaria($borrador))->toThrow(InvalidSchedule::class, 'publicada');
    });
})->group('scheduling');

it('valida la regla al guardar, no de madrugada', function (): void {
    // Una RRULE rota guardada hoy es un job que revienta dentro de un mes.
    enAcmeProg(function (): void {
        $plantilla = plantillaPublicada();

        expect(fn (): Schedule => programacionDiaria($plantilla, ['rrule' => 'FREQ=HOURLY']))
            ->toThrow(InvalidRecurrence::class);

        expect(Schedule::query()->count())->toBe(0);
    });
})->group('scheduling');

it('marca como incumplido solo lo pendiente cuyo cierre ya paso', function (): void {
    enAcmeProg(function (): void {
        Site::factory()->create();
        $programacion = programacionDiaria(plantillaPublicada(), ['toleranceMinutes' => 30]);
        resolve(MaterializeObligations::class)($programacion, '2026-09-15', '2026-09-16');

        // 15 de septiembre, 23:15 UTC: vencida (23:00) pero dentro de la
        // tolerancia (cierra 23:30). Todavia no es incumplida.
        $marcadas = resolve(MarkMissedObligations::class)(CarbonImmutable::parse('2026-09-15 23:15:00', 'UTC'));
        expect($marcadas)->toBe(0);

        // 23:31 UTC: ya cerro la del 15; la del 16 sigue pendiente.
        $marcadas = resolve(MarkMissedObligations::class)(CarbonImmutable::parse('2026-09-15 23:31:00', 'UTC'));

        expect($marcadas)->toBe(1)
            ->and(Obligation::query()->whereDate('occurrence_date', '2026-09-15')->first()?->status)->toBeInstanceOf(Missed::class)
            ->and(Obligation::query()->whereDate('occurrence_date', '2026-09-16')->first()?->status)->toBeInstanceOf(Pending::class);
    });
})->group('scheduling');

it('no reabre una obligacion incumplida como cumplida', function (): void {
    // missed -> fulfilled reescribiria el pasado: el cumplimiento de una semana
    // ya reportada cambiaria despues.
    enAcmeProg(function (): void {
        Site::factory()->create();
        $programacion = programacionDiaria(plantillaPublicada());
        resolve(MaterializeObligations::class)($programacion, '2026-09-15', '2026-09-15');
        resolve(MarkMissedObligations::class)(CarbonImmutable::parse('2026-09-16', 'UTC'));

        $obligacion = Obligation::query()->firstOrFail();

        expect(fn () => $obligacion->status->transitionTo(Fulfilled::class))
            ->toThrow(CouldNotPerformTransition::class);
    });
})->group('scheduling');

it('permite justificar un incumplimiento a posteriori', function (): void {
    enAcmeProg(function (): void {
        Site::factory()->create();
        $programacion = programacionDiaria(plantillaPublicada());
        resolve(MaterializeObligations::class)($programacion, '2026-09-15', '2026-09-15');
        resolve(MarkMissedObligations::class)(CarbonImmutable::parse('2026-09-16', 'UTC'));

        $obligacion = Obligation::query()->firstOrFail();
        $obligacion->status->transitionTo(Excused::class);

        expect($obligacion->refresh()->status)->toBeInstanceOf(Excused::class)
            // Y deja de contar para el KPI.
            ->and($obligacion->status->countsTowardsCompliance())->toBeFalse();
    });
})->group('scheduling');
