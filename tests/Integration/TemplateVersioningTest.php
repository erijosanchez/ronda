<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Ronda\Forms\Application\Actions\CreateTemplate;
use Ronda\Forms\Application\Actions\PublishTemplateVersion;
use Ronda\Forms\Application\Data\TemplateData;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Forms\Domain\TemplateStatus;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Identity\Domain\Models\User;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;

// Plantillas versionadas. Ver docs/adr/0012.
//
// Lo que se prueba aqui es el invariante que sostiene todo el motor: publicar
// una version nueva NO toca las anteriores. Sin eso, un envio aprobado en marzo
// deja de poder leerse en cuanto alguien edita el formulario.

const CLAVE_PLANTILLAS = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_PLANTILLAS,
    ));
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function enTenantForms(Closure $fn): void
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;
    $tenant->run($fn);
}

function propietariaForms(): User
{
    return User::query()->where('email', 'owner@acme.test')->firstOrFail();
}

function plantillaDeArqueo(): Template
{
    return resolve(CreateTemplate::class)(new TemplateData(
        code: 'ARQUEO',
        name: 'Arqueo de caja',
        description: 'Cierre diario de caja',
    ));
}

/**
 * @param  list<array<string, mixed>>  $extra
 */
function esquemaBasico(array $extra = []): FormSchema
{
    return FormSchema::fromArray([
        ['key' => 'monto', 'type' => 'money', 'label' => 'Monto contado', 'required' => true, 'reportable' => true],
        ['key' => 'observaciones', 'type' => 'text', 'label' => 'Observaciones'],
        ...$extra,
    ]);
}

it('crea la plantilla en borrador y sin versiones', function (): void {
    enTenantForms(function (): void {
        $plantilla = plantillaDeArqueo();

        expect($plantilla->status)->toBe(TemplateStatus::Draft)
            ->and($plantilla->current_version_id)->toBeNull()
            ->and($plantilla->versions()->count())->toBe(0)
            ->and($plantilla->status->canBeScheduled())->toBeFalse();
    });
})->group('forms');

it('publica la primera version y la deja vigente', function (): void {
    enTenantForms(function (): void {
        $plantilla = plantillaDeArqueo();

        $version = resolve(PublishTemplateVersion::class)(
            $plantilla, esquemaBasico(), propietariaForms(),
        );

        expect($version->number)->toBe(1)
            ->and($version->published_at)->not->toBeNull()
            ->and($version->published_by)->toBe(propietariaForms()->getKey());

        $plantilla->refresh();

        expect($plantilla->current_version_id)->toBe($version->getKey())
            ->and($plantilla->status)->toBe(TemplateStatus::Published)
            ->and($plantilla->status->canBeScheduled())->toBeTrue();
    });
})->group('forms');

it('no toca las versiones anteriores al publicar una nueva', function (): void {
    // El invariante del ADR 0012, y la razon de que exista este modulo.
    enTenantForms(function (): void {
        $plantilla = plantillaDeArqueo();
        $publicadora = propietariaForms();

        $primera = resolve(PublishTemplateVersion::class)($plantilla, esquemaBasico(), $publicadora);
        $esquemaOriginal = $primera->schema;

        $segunda = resolve(PublishTemplateVersion::class)(
            $plantilla->refresh(),
            esquemaBasico([
                ['key' => 'faltante', 'type' => 'money', 'label' => 'Faltante', 'reportable' => true],
            ]),
            $publicadora,
        );

        expect($segunda->number)->toBe(2);

        // La primera sigue exactamente como estaba.
        $primera->refresh();
        expect($primera->schema)->toBe($esquemaOriginal)
            ->and($primera->formSchema()->count())->toBe(2)
            ->and($primera->formSchema()->field('faltante'))->toBeNull();

        // Y la nueva es la vigente.
        expect($plantilla->refresh()->current_version_id)->toBe($segunda->getKey())
            ->and($segunda->formSchema()->count())->toBe(3);
    });
})->group('forms');

it('numera las versiones de forma correlativa por plantilla', function (): void {
    enTenantForms(function (): void {
        $arqueo = plantillaDeArqueo();
        $otra = resolve(CreateTemplate::class)(new TemplateData(code: 'LIMPIEZA', name: 'Limpieza'));
        $quien = propietariaForms();

        resolve(PublishTemplateVersion::class)($arqueo, esquemaBasico(), $quien);
        resolve(PublishTemplateVersion::class)($arqueo->refresh(), esquemaBasico(), $quien);
        $primeraDeOtra = resolve(PublishTemplateVersion::class)($otra, esquemaBasico(), $quien);

        // El correlativo es por plantilla, no global.
        expect($primeraDeOtra->number)->toBe(1)
            ->and($arqueo->refresh()->currentVersion->number)->toBe(2);
    });
})->group('forms');

it('distingue los campos reportables', function (): void {
    enTenantForms(function (): void {
        $plantilla = plantillaDeArqueo();

        $version = resolve(PublishTemplateVersion::class)(
            $plantilla, esquemaBasico(), propietariaForms(),
        );

        $reportables = $version->formSchema()->reportableFields();

        // Solo lo marcado se replica en submission_values (ADR 0012).
        expect($reportables)->toHaveCount(1)
            ->and($reportables[0]->key)->toBe('monto');
    });
})->group('forms');

it('no deja borrar una version que este vigente', function (): void {
    enTenantForms(function (): void {
        $plantilla = plantillaDeArqueo();
        $version = resolve(PublishTemplateVersion::class)(
            $plantilla, esquemaBasico(), propietariaForms(),
        );

        // RESTRICT en la clave foranea: borrarla dejaria la plantilla
        // apuntando al vacio.
        expect(fn () => $version->delete())
            ->toThrow(QueryException::class);
    });
})->group('forms');
