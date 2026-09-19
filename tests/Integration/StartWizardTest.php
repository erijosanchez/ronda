<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Forms\Application\Actions\CreateTemplate;
use Ronda\Forms\Application\Actions\PublishTemplateVersion;
use Ronda\Forms\Application\Data\TemplateData;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\RoleName;
use Ronda\Insights\Presentation\Livewire\Dashboard;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Application\Queries\OnboardingProgressQuery;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Platform\Domain\ValueObjects\OnboardingStep;
use Ronda\Platform\Presentation\Livewire\StartWizard;
use Ronda\Scheduling\Application\Actions\CreateSchedule;
use Ronda\Scheduling\Application\Actions\MaterializeObligations;
use Ronda\Scheduling\Application\Data\ScheduleData;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Scheduling\Domain\ScheduleScope;
use Ronda\Submissions\Application\Actions\SubmitReport;

// El asistente de arranque. RONDA-PLAN-MAESTRO.md sec. 15.3
//
// Lo que se prueba es que el avance sale de los DATOS y no de una bandera: cada
// paso se da por hecho cuando existe lo que produce, y solo entonces.

const CLAVE_ARRANQUE = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_ARRANQUE,
    ));
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function enAcmeArranque(Closure $fn): void
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;
    $tenant->run($fn);
}

function duenaDeArranque(): User
{
    $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();
    auth()->login($owner);

    return $owner;
}

it('empieza con los cinco pasos por hacer y propone el primero', function (): void {
    enAcmeArranque(function (): void {
        duenaDeArranque();

        $avance = resolve(OnboardingProgressQuery::class)();

        expect($avance->done())->toBe(0)
            ->and($avance->percentage())->toBe(0)
            ->and($avance->finished())->toBeFalse()
            ->and($avance->next())->toBe(OnboardingStep::Templates);

        Livewire::test(StartWizard::class)
            ->assertSee(__('Choose what gets checked'))
            ->assertDontSee(__('You are up and running'));
    });
})->group('tenancy');

it('da por hecho cada paso en cuanto existe lo que produce', function (): void {
    enAcmeArranque(function (): void {
        $owner = duenaDeArranque();
        $progreso = resolve(OnboardingProgressQuery::class);

        $plantilla = resolve(CreateTemplate::class)(new TemplateData(code: 'ARQUEO', name: 'Arqueo'));
        resolve(PublishTemplateVersion::class)($plantilla, FormSchema::fromArray([
            ['key' => 'monto', 'type' => 'money', 'label' => 'Monto', 'required' => true, 'reportable' => true],
        ]), $owner);

        expect($progreso()->next())->toBe(OnboardingStep::Sites);

        $sede = Site::factory()->create();

        expect($progreso()->next())->toBe(OnboardingStep::Team);

        // La duena no cuenta como equipo: ya estaba desde el alta.
        $encargada = User::create([
            'name' => 'Encargada',
            'email' => 'encargada@acme.test',
            'password' => CLAVE_ARRANQUE,
        ]);
        $encargada->assignRole(RoleName::SiteManager->value);
        $encargada->sites()->attach($sede->id);

        expect($progreso()->next())->toBe(OnboardingStep::Schedules);

        $programacion = resolve(CreateSchedule::class)(new ScheduleData(
            templateId: $plantilla->id,
            name: 'Arqueo diario',
            scope: ScheduleScope::Sites,
            rrule: 'FREQ=DAILY',
            windowStart: '08:00',
            windowEnd: '18:00',
            startsOn: '2026-09-01',
            toleranceMinutes: 30,
            siteIds: [$sede->id],
        ));

        expect($progreso()->next())->toBe(OnboardingStep::FirstReport)
            ->and($progreso()->done())->toBe(4)
            ->and($progreso()->percentage())->toBe(80);

        resolve(MaterializeObligations::class)($programacion, '2026-09-15', '2026-09-15');

        // Materializar no basta: el paso es ENVIAR el primer reporte.
        expect($progreso()->next())->toBe(OnboardingStep::FirstReport);

        $obligacion = Obligation::query()->where('site_id', $sede->id)->firstOrFail();

        resolve(SubmitReport::class)(
            $obligacion,
            $owner,
            ['monto' => '1520.40'],
            CarbonImmutable::parse('2026-09-15 20:00', 'UTC'),
        );

        expect($progreso()->finished())->toBeTrue()
            ->and($progreso()->percentage())->toBe(100);

        Livewire::test(StartWizard::class)->assertSee(__('You are up and running'));

        // Y el panel deja de insistir: ya no hay nada que proponer.
        Livewire::test(Dashboard::class)->assertDontSee(__('Continue setup'));
    });
})->group('tenancy');

it('no se lo ofrece a quien no puede montar la operacion', function (): void {
    enAcmeArranque(function (): void {
        $sede = Site::factory()->create();

        $encargada = User::create([
            'name' => 'Encargada',
            'email' => 'encargada@acme.test',
            'password' => CLAVE_ARRANQUE,
        ]);
        $encargada->assignRole(RoleName::SiteManager->value);
        $encargada->sites()->attach($sede->id);

        auth()->login($encargada->fresh());

        Livewire::test(StartWizard::class)->assertForbidden();

        // Y el panel tampoco se lo menciona: no podria hacer ninguno de los pasos.
        Livewire::test(Dashboard::class)->assertDontSee(__('Continue setup'));
    });
})->group('tenancy');

it('el panel avisa mientras la cuenta este a medio montar', function (): void {
    enAcmeArranque(function (): void {
        duenaDeArranque();

        Livewire::test(Dashboard::class)
            ->assertSee(__('Your account is not running yet'))
            ->assertSee(__('Continue setup'));
    });
})->group('tenancy');
