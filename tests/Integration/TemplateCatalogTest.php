<?php

declare(strict_types=1);

use Livewire\Livewire;
use Ronda\Forms\Application\Actions\InstallCatalogTemplate;
use Ronda\Forms\Domain\CatalogTemplate;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Forms\Domain\TemplateStatus;
use Ronda\Forms\Domain\ValueObjects\FieldType;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Forms\Presentation\Livewire\TemplateCatalog;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\RoleName;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Scheduling\Application\Actions\CreateSchedule;
use Ronda\Scheduling\Application\Data\ScheduleData;
use Ronda\Scheduling\Domain\ScheduleScope;

// El catalogo de arranque. RONDA-PLAN-MAESTRO.md sec. 3.5
//
// «Cada una es configuracion del motor, no codigo»: lo que se prueba es que los
// cinco esquemas se sostienen contra las mismas reglas que cualquier plantilla
// del cliente, y que instalar deja una plantilla normal, publicada y
// programable.

const CLAVE_CATALOGO = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_CATALOGO,
    ));
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function enAcmeCatalogo(Closure $fn): mixed
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;

    return $tenant->run($fn);
}

function duenaDelCatalogo(): User
{
    $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();
    auth()->login($owner);

    return $owner;
}

it('las cinco plantillas del catalogo forman un esquema valido', function (CatalogTemplate $plantilla): void {
    // Se construyen con las mismas reglas que las del cliente: claves unicas,
    // opciones donde toca, condiciones que apuntan a campos que existen.
    $schema = FormSchema::fromArray($plantilla->schema());

    // Construirlo ya valida lo caro: claves repetidas, opciones donde toca y
    // condiciones que apuntan a campos inexistentes (FormSchema las rechaza).
    expect($schema->count())->toBeGreaterThan(3)
        // Toda plantilla del catalogo tiene algo que medir.
        ->and($schema->reportableFields())->not->toBeEmpty();
})->with(CatalogTemplate::cases())->group('forms');

it('instala una plantilla del catalogo ya publicada y lista para programar', function (): void {
    enAcmeCatalogo(function (): void {
        $owner = duenaDelCatalogo();

        $plantilla = resolve(InstallCatalogTemplate::class)(CatalogTemplate::CashCount, $owner);

        expect($plantilla->code)->toBe('ARQUEO-CAJA')
            ->and($plantilla->status)->toBe(TemplateStatus::Published)
            ->and($plantilla->currentVersion?->number)->toBe(1)
            // Con sus campos, incluida la foto obligatoria del arqueo.
            ->and($plantilla->currentVersion?->formSchema()->field('foto_arqueo')?->type)->toBe(FieldType::Photo);

        // Y se puede programar sin tocar nada mas.
        $programacion = resolve(CreateSchedule::class)(new ScheduleData(
            templateId: $plantilla->id,
            name: 'Arqueo diario',
            scope: ScheduleScope::AllSites,
            rrule: 'FREQ=DAILY',
            windowStart: '08:00',
            windowEnd: '18:00',
            startsOn: '2026-09-01',
        ));

        expect($programacion->template_id)->toBe($plantilla->id);
    });
})->group('forms');

it('instalar dos veces no duplica ni pisa lo que el cliente cambio', function (): void {
    enAcmeCatalogo(function (): void {
        $owner = duenaDelCatalogo();
        $instalar = resolve(InstallCatalogTemplate::class);

        $primera = $instalar(CatalogTemplate::BankDeposit, $owner);
        $primera->update(['name' => 'Deposito de la cobranza']);

        $segunda = $instalar(CatalogTemplate::BankDeposit, $owner);

        expect($segunda->id)->toBe($primera->id)
            ->and($segunda->fresh()?->name)->toBe('Deposito de la cobranza')
            ->and(Template::query()->where('code', 'DEPOSITO')->count())->toBe(1)
            // Y sin una version nueva que no pidio nadie.
            ->and($segunda->versions()->count())->toBe(1);
    });
})->group('forms');

it('instala desde la pantalla y marca las que ya estan', function (): void {
    enAcmeCatalogo(function (): void {
        duenaDelCatalogo();

        Livewire::test(TemplateCatalog::class)
            ->assertViewHas('installed', fn ($instaladas): bool => $instaladas->isEmpty())
            ->assertSee('Arqueo de caja')
            ->call('install', CatalogTemplate::CleaningChecklist->value)
            ->assertHasNoErrors()
            ->assertRedirect(route('templates.index'));

        expect(Template::query()->where('code', 'LIMPIEZA')->exists())->toBeTrue();

        Livewire::test(TemplateCatalog::class)
            ->assertViewHas('installed', fn ($instaladas): bool => $instaladas->has('LIMPIEZA'));
    });
})->group('forms');

it('ignora un codigo que no esta en el catalogo', function (): void {
    enAcmeCatalogo(function (): void {
        duenaDelCatalogo();

        Livewire::test(TemplateCatalog::class)
            ->call('install', 'PLANTILLA-INVENTADA')
            ->assertHasErrors(['catalog']);

        expect(Template::query()->count())->toBe(0);
    });
})->group('forms');

it('no deja instalar a quien no puede publicar', function (): void {
    enAcmeCatalogo(function (): void {
        // Un supervisor ve plantillas, pero publicarlas es otra cosa: es lo que
        // empieza a exigir entregas a las sedes.
        $supervisora = User::create(['name' => 'Sup', 'email' => 'sup@acme.test', 'password' => CLAVE_CATALOGO]);
        $supervisora->assignRole(RoleName::Supervisor->value);
        auth()->login($supervisora->fresh());

        Livewire::test(TemplateCatalog::class)
            ->assertOk()
            ->assertViewHas('canInstall', false)
            ->call('install', CatalogTemplate::Incident->value)
            ->assertForbidden();

        expect(Template::query()->count())->toBe(0);
    });
})->group('forms');

it('sirve el catalogo dentro del layout y lo ofrece desde plantillas', function (): void {
    $url = 'http://acme.ronda.test';
    $this->post($url.'/login', ['email' => 'owner@acme.test', 'password' => CLAVE_CATALOGO]);

    $this->get($url.'/plantillas')->assertOk()->assertSee('Del catálogo');

    $this->get($url.'/plantillas/catalogo')
        ->assertOk()
        ->assertSee('Catálogo de plantillas')
        ->assertSee('Reporte de incidencias')
        ->assertSee('Semanal');
})->group('forms');
