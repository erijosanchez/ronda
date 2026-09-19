<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Storage;
use Laravel\Pennant\Feature;
use Livewire\Livewire;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Evidence\Application\Actions\StoreEvidence;
use Ronda\Evidence\Application\Data\EvidenceUpload;
use Ronda\Evidence\Domain\EvidenceKind;
use Ronda\Evidence\Domain\Models\Attachment;
use Ronda\Forms\Application\Actions\CreateTemplate;
use Ronda\Forms\Application\Actions\PublishTemplateVersion;
use Ronda\Forms\Application\Data\TemplateData;
use Ronda\Forms\Domain\CatalogTemplate;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Forms\Presentation\Livewire\TemplateCatalog;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\RoleName;
use Ronda\Platform\Application\Actions\ChangeTenantPlan;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Exceptions\PlanLimit;
use Ronda\Platform\Domain\Exceptions\PlanLimitExceeded;
use Ronda\Platform\Domain\Models\Plan;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Platform\Domain\PlanCode;
use Ronda\Platform\Domain\PlanFeature;
use Ronda\Platform\Presentation\Livewire\PlanAndUsage;
use Ronda\Scheduling\Application\Actions\CreateSchedule;
use Ronda\Scheduling\Application\Actions\MaterializeObligations;
use Ronda\Scheduling\Application\Data\ScheduleData;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Scheduling\Domain\ScheduleScope;
use Ronda\Submissions\Application\Actions\SubmitReport;
use Tests\Support\EvidenceFixtures;

// Planes y limites. RONDA-PLAN-MAESTRO.md sec. 3.6 y 15.1
//
// Lo que se prueba es que el limite lo aplica la ACTION y no la pantalla —por
// ahi pasan el disenador, el catalogo y manana la API—, que se corta antes de
// escribir nada, y que lo que decide es el plan que el cliente tiene ahora.

const CLAVE_PLANES = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    // Sin catalogo de planes no hay limites que aplicar: es dato de negocio,
    // no de desarrollo, y por eso se siembra tambien aqui.
    $this->seed(PlanSeeder::class);

    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_PLANES,
    ));

    Storage::fake(StoreEvidence::DISK);
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function enAcmePlanes(Closure $fn): mixed
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;

    return $tenant->run($fn);
}

function duenaDePlanes(): User
{
    $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();
    auth()->login($owner);

    return $owner;
}

/**
 * Cambia el plan del cliente de la prueba.
 *
 * Vacia la cache de pennant a proposito: en una peticion de verdad lo hace
 * Octane al recibirla, y sin esto la prueba leeria la bandera del plan viejo.
 */
function conPlan(PlanCode $code): void
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;

    resolve(ChangeTenantPlan::class)($tenant, $code);
    $tenant->refresh();

    // Lo que hace una peticion nueva: el plan se resuelve una vez por peticion
    // (enlace `scoped`) y pennant guarda lo resuelto en memoria. Sin esto, la
    // prueba seguiria leyendo el plan anterior dentro del mismo proceso.
    app()->forgetScopedInstances();
    Feature::flushCache();
}

/**
 * Obligacion abierta de una plantilla que pide foto, para gastar espacio.
 */
function obligacionDePlanes(): Obligation
{
    $sede = Site::factory()->create();
    $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();

    $plantilla = resolve(CreateTemplate::class)(new TemplateData(code: 'APERTURA', name: 'Apertura'));
    resolve(PublishTemplateVersion::class)($plantilla, FormSchema::fromArray([
        ['key' => 'foto', 'type' => 'photo', 'label' => 'Foto del local', 'required' => true],
    ]), $owner);

    $programacion = resolve(CreateSchedule::class)(new ScheduleData(
        templateId: $plantilla->id,
        name: 'Apertura diaria',
        scope: ScheduleScope::Sites,
        rrule: 'FREQ=DAILY',
        windowStart: '08:00',
        windowEnd: '18:00',
        startsOn: '2026-09-01',
        skipHolidays: false,
        siteIds: [$sede->id],
    ));

    resolve(MaterializeObligations::class)($programacion, '2026-09-16', '2026-09-16');

    return Obligation::query()->where('site_id', $sede->id)->firstOrFail();
}

it('da el plan de entrada a quien se registra', function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;

    expect($tenant->plan)->toBeInstanceOf(Plan::class)
        ->and($tenant->plan?->code())->toBe(PlanCode::Starter)
        ->and($tenant->plan?->limits()->maxTemplates)->toBe(3);
})->group('tenancy');

it('corta la cuarta plantilla en Starter y dice con que numero', function (): void {
    enAcmePlanes(function (): void {
        duenaDePlanes();
        $crear = resolve(CreateTemplate::class);

        foreach (['UNO', 'DOS', 'TRES'] as $codigo) {
            $crear(new TemplateData(code: $codigo, name: $codigo));
        }

        try {
            $crear(new TemplateData(code: 'CUATRO', name: 'Cuatro'));
            $this->fail('La cuarta plantilla no deberia haberse creado.');
        } catch (PlanLimitExceeded $e) {
            expect($e->limit)->toBe(PlanLimit::Templates)
                ->and($e->limitValue)->toBe(3);
        }

        // Y no queda una plantilla a medias: se corta antes de escribir.
        expect(Template::query()->count())->toBe(3);
    });
})->group('tenancy');

it('lo explica en el catalogo en vez de reventar', function (): void {
    enAcmePlanes(function (): void {
        duenaDePlanes();
        $crear = resolve(CreateTemplate::class);

        foreach (['UNO', 'DOS', 'TRES'] as $codigo) {
            $crear(new TemplateData(code: $codigo, name: $codigo));
        }

        Livewire::test(TemplateCatalog::class)
            ->call('install', CatalogTemplate::CashCount->value)
            ->assertHasErrors('catalog');

        expect(Template::query()->count())->toBe(3);
    });
})->group('tenancy');

it('deja de cortar al subir de plan, con lo ya creado intacto', function (): void {
    enAcmePlanes(function (): void {
        duenaDePlanes();
        $crear = resolve(CreateTemplate::class);

        foreach (['UNO', 'DOS', 'TRES'] as $codigo) {
            $crear(new TemplateData(code: $codigo, name: $codigo));
        }
    });

    conPlan(PlanCode::Pro);

    enAcmePlanes(function (): void {
        duenaDePlanes();

        resolve(CreateTemplate::class)(new TemplateData(code: 'CUATRO', name: 'Cuatro'));

        expect(Template::query()->count())->toBe(4);
    });

    // Y al volver a Starter, las cuatro siguen ahi: bajar de plan no borra
    // trabajo del cliente, solo impide crear mas.
    conPlan(PlanCode::Starter);

    enAcmePlanes(function (): void {
        duenaDePlanes();

        expect(Template::query()->count())->toBe(4)
            ->and(fn (): Template => resolve(CreateTemplate::class)(new TemplateData(code: 'CINCO', name: 'Cinco')))
            ->toThrow(PlanLimitExceeded::class);
    });
})->group('tenancy');

it('no deja que una sede pase del espacio que le da su plan', function (): void {
    enAcmePlanes(function (): void {
        $owner = duenaDePlanes();

        $envio = resolve(SubmitReport::class)(
            obligacionDePlanes(),
            $owner,
            [],
            CarbonImmutable::parse('2026-09-16 20:00:00', 'UTC'),
            ['foto' => [new EvidenceUpload(contents: EvidenceFixtures::jpeg(), originalName: 'una.jpg')]],
        );

        // Se simula que esa sede ya lleno su giga, sin mover un giga de bytes.
        Attachment::query()->update(['bytes' => 1024 ** 3]);

        expect(fn (): mixed => resolve(StoreEvidence::class)(
            $envio,
            'foto',
            EvidenceKind::Photo,
            new EvidenceUpload(contents: EvidenceFixtures::jpeg(), originalName: 'otra.jpg'),
            $owner,
        ))->toThrow(PlanLimitExceeded::class);

        // Y el archivo rechazado no llego al bucket: se corta antes de escribir.
        expect(Attachment::query()->count())->toBe(1)
            ->and(Storage::disk(StoreEvidence::DISK)->allFiles())->toHaveCount(1);
    });
})->group('tenancy');

it('enciende las funciones que trae el plan y solo esas', function (): void {
    enAcmePlanes(function (): void {
        duenaDePlanes();

        expect(Feature::active(PlanFeature::WhatsApp->value))->toBeFalse()
            ->and(Feature::active(PlanFeature::Api->value))->toBeFalse();
    });

    conPlan(PlanCode::Pro);

    enAcmePlanes(function (): void {
        duenaDePlanes();

        expect(Feature::active(PlanFeature::WhatsApp->value))->toBeTrue()
            ->and(Feature::active(PlanFeature::Api->value))->toBeTrue()
            // El SSO es opcional en el §3.6: no viene con Pro.
            ->and(Feature::active(PlanFeature::SingleSignOn->value))->toBeFalse();
    });
})->group('tenancy');

it('muestra el plan, el consumo y lo que no incluye', function (): void {
    enAcmePlanes(function (): void {
        duenaDePlanes();
        resolve(CreateTemplate::class)(new TemplateData(code: 'UNO', name: 'Uno'));

        Livewire::test(PlanAndUsage::class)
            ->assertSee('Starter')
            ->assertSee(__('Plan and usage'))
            ->assertSee(__('Not in this plan'));
    });
})->group('tenancy');

it('no le ensena el plan a quien no administra la cuenta', function (): void {
    enAcmePlanes(function (): void {
        $encargada = User::create([
            'name' => 'Encargada',
            'email' => 'encargada@acme.test',
            'password' => CLAVE_PLANES,
        ]);
        $encargada->assignRole(RoleName::SiteManager->value);

        auth()->login($encargada->fresh());

        Livewire::test(PlanAndUsage::class)->assertForbidden();
    });
})->group('tenancy');
