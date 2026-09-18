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
use Ronda\Identity\Domain\RoleName;
use Ronda\Insights\Application\Actions\RecalculateKpis;
use Ronda\Insights\Application\Data\KpiFilter;
use Ronda\Insights\Application\Queries\KpiSummaryQuery;
use Ronda\Insights\Application\Queries\RepeatOffendersQuery;
use Ronda\Insights\Application\Queries\SiteRankingQuery;
use Ronda\Insights\Domain\Models\KpiDaily;
use Ronda\Insights\Presentation\Livewire\Dashboard;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Scheduling\Application\Actions\CreateSchedule;
use Ronda\Scheduling\Application\Actions\MaterializeObligations;
use Ronda\Scheduling\Application\Data\ScheduleData;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Scheduling\Domain\ScheduleScope;
use Ronda\Scheduling\Domain\States\Excused;
use Ronda\Scheduling\Domain\States\Missed;
use Ronda\Submissions\Application\Actions\SubmitReport;
use Ronda\Workflow\Application\Actions\ApproveSubmission;
use Ronda\Workflow\Application\Actions\CorrectSubmission;
use Ronda\Workflow\Application\Actions\RejectSubmission;

// KPI materializados. RONDA-PLAN-MAESTRO.md sec. 9.6
//
// Lo que se prueba: que las cifras salen de lo que de verdad paso, que
// recalcular no duplica ni deja rastros viejos, y que nadie ve el cumplimiento
// de sedes que no le tocan.

const CLAVE_KPI = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_KPI,
    ));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();

    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function enAcmeKpi(Closure $fn): mixed
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;

    return $tenant->run($fn);
}

function personaKpi(string $email, RoleName $rol, ?Site $sede = null): User
{
    $user = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => CLAVE_KPI]);
    $user->assignRole($rol->value);

    if ($sede instanceof Site) {
        $user->sites()->attach($sede->id);
    }

    return $user->fresh();
}

/**
 * Programa un arqueo diario en la sede y materializa tres dias:
 * 15, 16 y 17 de septiembre de 2026. Ventana 08:00-18:00 de Lima.
 */
function tresDiasDeArqueo(Site $sede): Template
{
    $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();

    $plantilla = resolve(CreateTemplate::class)(new TemplateData(code: 'ARQ-'.$sede->id, name: 'Arqueo '.$sede->code));
    resolve(PublishTemplateVersion::class)($plantilla, FormSchema::fromArray([
        ['key' => 'monto', 'type' => 'money', 'label' => 'Monto', 'required' => true],
    ]), $owner);

    $programacion = resolve(CreateSchedule::class)(new ScheduleData(
        templateId: $plantilla->id,
        name: 'Arqueo diario '.$sede->code,
        scope: ScheduleScope::Sites,
        rrule: 'FREQ=DAILY',
        windowStart: '08:00',
        windowEnd: '18:00',
        startsOn: '2026-09-01',
        // Con media hora de gracia: sirve para medir entregas tardias que
        // siguen siendo entregas.
        toleranceMinutes: 30,
        skipHolidays: false,
        siteIds: [$sede->id],
    ));

    resolve(MaterializeObligations::class)($programacion, '2026-09-15', '2026-09-17');

    return $plantilla->refresh();
}

function obligacionDe(Site $sede, string $dia): Obligation
{
    return Obligation::query()
        ->where('site_id', $sede->id)
        ->whereDate('occurrence_date', $dia)
        ->firstOrFail();
}

function filtroDeSeptiembre(?int $siteId = null, ?int $templateId = null): KpiFilter
{
    return new KpiFilter(from: '2026-09-15', to: '2026-09-17', siteId: $siteId, templateId: $templateId);
}

it('cuenta lo cumplido, lo incumplido y lo justificado por dia, sede y plantilla', function (): void {
    enAcmeKpi(function (): void {
        $sede = Site::factory()->create();
        tresDiasDeArqueo($sede);
        $encargada = personaKpi('encargada@acme.test', RoleName::SiteManager, $sede);

        // 15: entregado a tiempo. 16: incumplido. 17: justificado.
        resolve(SubmitReport::class)(
            obligacionDe($sede, '2026-09-15'),
            $encargada,
            ['monto' => '100'],
            CarbonImmutable::parse('2026-09-15 20:00', 'UTC'),
        );
        obligacionDe($sede, '2026-09-16')->status->transitionTo(Missed::class);
        obligacionDe($sede, '2026-09-17')->status->transitionTo(Excused::class);

        expect(resolve(RecalculateKpis::class)('2026-09-15', '2026-09-17'))->toBe(3);

        $resumen = resolve(KpiSummaryQuery::class)(filtroDeSeptiembre());

        expect($resumen->fulfilled)->toBe(1)
            ->and($resumen->missed)->toBe(1)
            ->and($resumen->excused)->toBe(1)
            // El justificado no cuenta: 1 de 2, no 1 de 3.
            ->and($resumen->compliance())->toBe(50.0)
            ->and($resumen->onTime)->toBe(1)
            ->and($resumen->punctuality())->toBe(100.0);

        // Una fila por dia, sede y plantilla.
        expect(KpiDaily::query()->count())->toBe(3)
            ->and(KpiDaily::query()->whereDate('kpi_date', '2026-09-16')->value('missed'))->toBe(1);
    });
})->group('insights');

it('mide la puntualidad y el retraso de lo entregado tarde', function (): void {
    enAcmeKpi(function (): void {
        $sede = Site::factory()->create();
        tresDiasDeArqueo($sede);
        $encargada = personaKpi('encargada@acme.test', RoleName::SiteManager, $sede);

        // Vence a las 23:00 UTC; se entrega a las 23:20, dentro de la
        // tolerancia pero tarde.
        resolve(SubmitReport::class)(
            obligacionDe($sede, '2026-09-15'),
            $encargada,
            ['monto' => '100'],
            CarbonImmutable::parse('2026-09-15 23:20', 'UTC'),
        );

        resolve(RecalculateKpis::class)('2026-09-15', '2026-09-17');
        $resumen = resolve(KpiSummaryQuery::class)(filtroDeSeptiembre());

        expect($resumen->late)->toBe(1)
            ->and($resumen->onTime)->toBe(0)
            ->and($resumen->punctuality())->toBe(0.0)
            ->and($resumen->averageMinutesLate())->toBe(20.0);
    });
})->group('insights');

it('cuenta como calidad lo aprobado a la primera y mide el tiempo de revision', function (): void {
    enAcmeKpi(function (): void {
        $sede = Site::factory()->create();
        tresDiasDeArqueo($sede);
        $encargada = personaKpi('encargada@acme.test', RoleName::SiteManager, $sede);
        $supervisora = personaKpi('supervisora@acme.test', RoleName::Supervisor, $sede);
        $entregar = resolve(SubmitReport::class);

        // El 15 se aprueba a la primera; el 16 se rechaza, se corrige y se
        // aprueba: cuenta como aprobado, pero NO a la primera.
        $bueno = $entregar(obligacionDe($sede, '2026-09-15'), $encargada, ['monto' => '100'], CarbonImmutable::parse('2026-09-15 20:00', 'UTC'));
        $corregido = $entregar(obligacionDe($sede, '2026-09-16'), $encargada, ['monto' => '1'], CarbonImmutable::parse('2026-09-16 20:00', 'UTC'));

        resolve(ApproveSubmission::class)($bueno, $supervisora);
        resolve(RejectSubmission::class)($corregido, $supervisora, 'El monto no cuadra');
        resolve(CorrectSubmission::class)($corregido->refresh(), $encargada, ['monto' => '150']);
        resolve(ApproveSubmission::class)($corregido->refresh(), $supervisora);

        resolve(RecalculateKpis::class)('2026-09-15', '2026-09-17');
        $resumen = resolve(KpiSummaryQuery::class)(filtroDeSeptiembre());

        expect($resumen->approved)->toBe(2)
            ->and($resumen->approvedFirstTry)->toBe(1)
            ->and($resumen->quality())->toBe(50.0)
            ->and($resumen->reviewsResolved)->toBe(2)
            ->and($resumen->averageReviewMinutes())->not->toBeNull();
    });
})->group('insights');

it('recalcular el mismo rango no duplica y refleja lo que cambio', function (): void {
    enAcmeKpi(function (): void {
        $sede = Site::factory()->create();
        tresDiasDeArqueo($sede);
        $encargada = personaKpi('encargada@acme.test', RoleName::SiteManager, $sede);
        $recalcular = resolve(RecalculateKpis::class);

        obligacionDe($sede, '2026-09-15')->status->transitionTo(Missed::class);
        $recalcular('2026-09-15', '2026-09-17');

        expect(resolve(KpiSummaryQuery::class)(filtroDeSeptiembre())->missed)->toBe(1);

        // Se justifica a posteriori: la cifra de un dia ya cerrado cambia.
        obligacionDe($sede, '2026-09-15')->status->transitionTo(Excused::class);
        $recalcular('2026-09-15', '2026-09-17');
        $resumen = resolve(KpiSummaryQuery::class)(filtroDeSeptiembre());

        expect($resumen->missed)->toBe(0)
            ->and($resumen->excused)->toBe(1)
            ->and(KpiDaily::query()->count())->toBe(3);

        // Y una entrega nueva se refleja en la siguiente pasada.
        resolve(SubmitReport::class)(
            obligacionDe($sede, '2026-09-16'),
            $encargada,
            ['monto' => '100'],
            CarbonImmutable::parse('2026-09-16 20:00', 'UTC'),
        );
        $recalcular('2026-09-15', '2026-09-17');

        expect(resolve(KpiSummaryQuery::class)(filtroDeSeptiembre())->fulfilled)->toBe(1);
    });
})->group('insights');

it('filtra por sede y por plantilla, y ordena el ranking por el peor cumplimiento', function (): void {
    enAcmeKpi(function (): void {
        $buena = Site::factory()->create(['name' => 'Sede Buena']);
        $mala = Site::factory()->create(['name' => 'Sede Mala']);
        $plantillaBuena = tresDiasDeArqueo($buena);
        tresDiasDeArqueo($mala);
        $encargada = personaKpi('encargada@acme.test', RoleName::SiteManager, $buena);
        $encargada->sites()->attach($mala->id);
        $entregar = resolve(SubmitReport::class);

        foreach (['2026-09-15', '2026-09-16', '2026-09-17'] as $dia) {
            $entregar(obligacionDe($buena, $dia), $encargada, ['monto' => '100'], CarbonImmutable::parse($dia.' 20:00', 'UTC'));
        }

        $entregar(obligacionDe($mala, '2026-09-15'), $encargada, ['monto' => '100'], CarbonImmutable::parse('2026-09-15 20:00', 'UTC'));
        obligacionDe($mala, '2026-09-16')->status->transitionTo(Missed::class);
        obligacionDe($mala, '2026-09-17')->status->transitionTo(Missed::class);

        resolve(RecalculateKpis::class)('2026-09-15', '2026-09-17');

        // Global: 4 de 6.
        expect(resolve(KpiSummaryQuery::class)(filtroDeSeptiembre())->compliance())->toBeGreaterThan(66.0)
            // Por sede.
            ->and(resolve(KpiSummaryQuery::class)(filtroDeSeptiembre($buena->id))->compliance())->toBe(100.0)
            ->and(resolve(KpiSummaryQuery::class)(filtroDeSeptiembre($mala->id))->compliance())->toBeGreaterThan(33.0)
            // Por plantilla: la de la sede buena solo cuenta sus tres dias.
            ->and(resolve(KpiSummaryQuery::class)(filtroDeSeptiembre(templateId: $plantillaBuena->id))->fulfilled)->toBe(3);

        $ranking = resolve(SiteRankingQuery::class)(filtroDeSeptiembre());

        // Primero la peor.
        expect($ranking->items()[0]['site_name'])->toBe('Sede Mala')
            ->and($ranking->items()[1]['site_name'])->toBe('Sede Buena');

        // Reincidencia con umbral 2: la mala, con su plantilla.
        $reincidentes = resolve(RepeatOffendersQuery::class)(filtroDeSeptiembre(), threshold: 2);

        expect($reincidentes)->toHaveCount(1)
            ->and($reincidentes[0]['site_name'])->toBe('Sede Mala')
            ->and($reincidentes[0]['missed'])->toBe(2);
    });
})->group('insights');

it('no deja ver el cumplimiento de sedes que no se alcanzan', function (): void {
    enAcmeKpi(function (): void {
        $mia = Site::factory()->create(['name' => 'Sede Mia']);
        $ajena = Site::factory()->create(['name' => 'Sede Ajena']);
        tresDiasDeArqueo($mia);
        tresDiasDeArqueo($ajena);

        obligacionDe($mia, '2026-09-15')->status->transitionTo(Missed::class);
        obligacionDe($ajena, '2026-09-15')->status->transitionTo(Missed::class);
        obligacionDe($ajena, '2026-09-16')->status->transitionTo(Missed::class);

        resolve(RecalculateKpis::class)('2026-09-15', '2026-09-17');

        // Una supervisora solo de «Sede Mia».
        auth()->login(personaKpi('supervisora@acme.test', RoleName::Supervisor, $mia));

        $resumen = resolve(KpiSummaryQuery::class)(filtroDeSeptiembre());
        $ranking = resolve(SiteRankingQuery::class)(filtroDeSeptiembre());

        expect($resumen->missed)->toBe(1)
            ->and($ranking->total())->toBe(1)
            ->and($ranking->items()[0]['site_name'])->toBe('Sede Mia');
    });
})->group('insights');

it('muestra los indicadores a quien puede ver reportes y la bienvenida a quien no', function (): void {
    enAcmeKpi(function (): void {
        $sede = Site::factory()->create();
        tresDiasDeArqueo($sede);
        obligacionDe($sede, '2026-09-15')->status->transitionTo(Missed::class);
        resolve(RecalculateKpis::class)('2026-09-15', '2026-09-17');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-17 12:00', 'UTC'));

        // La supervisora tiene `report.view`.
        auth()->login(personaKpi('supervisora@acme.test', RoleName::Supervisor, $sede));

        Livewire::test(Dashboard::class)
            ->assertViewHas('showKpis', true)
            ->assertViewHas('summary', fn ($resumen): bool => $resumen->missed === 1)
            ->assertSee('Cumplimiento');

        // La encargada no: ve su bienvenida y su acceso a pendientes.
        auth()->login(personaKpi('encargada@acme.test', RoleName::SiteManager, $sede));

        Livewire::test(Dashboard::class)
            ->assertViewHas('showKpis', false)
            ->assertSee('Tu día')
            ->assertDontSee('Sedes a vigilar');
    });
})->group('insights');

it('recalcula desde el comando en todos los tenants', function (): void {
    enAcmeKpi(function (): void {
        $sede = Site::factory()->create();
        tresDiasDeArqueo($sede);
        obligacionDe($sede, '2026-09-15')->status->transitionTo(Missed::class);
    });

    // El comando recalcula alrededor de hoy: se coloca el reloj en esos dias.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-17 06:00', 'UTC'));

    $this->artisan('kpi:recalculate --sync --days=7')->assertSuccessful();

    enAcmeKpi(function (): void {
        expect(KpiDaily::query()->count())->toBe(3)
            ->and((int) KpiDaily::query()->sum('missed'))->toBe(1);
    });
})->group('insights');

it('sirve el panel con los indicadores dentro del layout', function (): void {
    enAcmeKpi(function (): void {
        $sede = Site::factory()->create();
        tresDiasDeArqueo($sede);
        obligacionDe($sede, '2026-09-15')->status->transitionTo(Missed::class);
        resolve(RecalculateKpis::class)('2026-09-15', '2026-09-17');
    });

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-17 12:00', 'UTC'));

    $url = 'http://acme.ronda.test';
    $this->post($url.'/login', ['email' => 'owner@acme.test', 'password' => CLAVE_KPI]);

    $this->get($url.'/panel')
        ->assertOk()
        ->assertSee('Cumplimiento')
        ->assertSee('Sedes a vigilar');
})->group('insights');
