<?php

declare(strict_types=1);

use Livewire\Livewire;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Forms\Domain\TemplateStatus;
use Ronda\Forms\Presentation\Livewire\TemplateDesigner;
use Ronda\Forms\Presentation\Livewire\TemplateList;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\PermissionName;
use Ronda\Identity\Domain\RoleName;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;

// El disenador de plantillas. RONDA-PLAN-MAESTRO.md sec. 9.2
//
// No hay borradores: guardar publica. Lo que se prueba aqui es que el
// recorrido de la pantalla acaba en una version publicada coherente, y que un
// esquema incoherente se explica en vez de reventar.

const CLAVE_DISENO = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_DISENO,
    ));
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function enTenantDiseno(Closure $fn): void
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;
    $tenant->run($fn);
}

function comoDuena(): User
{
    $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();
    auth()->login($owner);

    return $owner;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function campo(string $key, string $type = 'text', array $overrides = []): array
{
    return [
        'key' => $key,
        'type' => $type,
        'label' => ucfirst($key),
        'help' => '',
        'required' => false,
        'reportable' => false,
        'options' => '',
        ...$overrides,
    ];
}

it('publica una plantilla nueva desde el disenador', function (): void {
    enTenantDiseno(function (): void {
        comoDuena();

        Livewire::test(TemplateDesigner::class)
            ->set('code', 'ARQUEO')
            ->set('name', 'Arqueo de caja')
            ->set('fields', [
                campo('monto', 'money', ['required' => true, 'reportable' => true]),
                campo('observaciones'),
            ])
            ->call('publish')
            ->assertHasNoErrors()
            ->assertRedirect(route('templates.index'));

        $plantilla = Template::query()->where('code', 'ARQUEO')->firstOrFail();

        expect($plantilla->status)->toBe(TemplateStatus::Published)
            ->and($plantilla->currentVersion->number)->toBe(1)
            ->and($plantilla->currentVersion->formSchema()->count())->toBe(2)
            ->and($plantilla->currentVersion->formSchema()->field('monto')?->required)->toBeTrue();
    });
})->group('forms');

it('convierte las opciones escritas por lineas en una lista', function (): void {
    enTenantDiseno(function (): void {
        comoDuena();

        Livewire::test(TemplateDesigner::class)
            ->set('code', 'TURNOS')
            ->set('name', 'Turnos')
            ->set('fields', [
                campo('turno', 'select', ['options' => "manana\ntarde\n\nnoche  "]),
            ])
            ->call('publish')
            ->assertHasNoErrors();

        $opciones = Template::query()->where('code', 'TURNOS')->firstOrFail()
            ->currentVersion->formSchema()->field('turno')?->options;

        // Se limpian los espacios y se descartan las lineas vacias.
        expect($opciones)->toBe(['manana', 'tarde', 'noche']);
    });
})->group('forms');

it('publica una version nueva al editar y deja la anterior intacta', function (): void {
    enTenantDiseno(function (): void {
        comoDuena();

        Livewire::test(TemplateDesigner::class)
            ->set('code', 'ARQUEO')
            ->set('name', 'Arqueo')
            ->set('fields', [campo('monto', 'money')])
            ->call('publish')
            ->assertHasNoErrors();

        $plantilla = Template::query()->where('code', 'ARQUEO')->firstOrFail();
        $primera = $plantilla->currentVersion;

        // El disenador parte de la version vigente y se le anade un campo.
        Livewire::test(TemplateDesigner::class, ['template' => $plantilla])
            ->assertSet('code', 'ARQUEO')
            ->call('addField')
            ->set('fields.1', campo('faltante', 'money'))
            ->call('publish')
            ->assertHasNoErrors();

        $plantilla->refresh();

        expect($plantilla->currentVersion->number)->toBe(2)
            ->and($plantilla->currentVersion->formSchema()->count())->toBe(2)
            // La version 1 sigue con un solo campo.
            ->and($primera->refresh()->formSchema()->count())->toBe(1);
    });
})->group('forms');

it('explica un esquema incoherente en vez de reventar', function (): void {
    enTenantDiseno(function (): void {
        comoDuena();

        // Una firma no tiene valor escalar que indexar: el dominio lo rechaza y
        // la pantalla lo traduce a un error de formulario.
        Livewire::test(TemplateDesigner::class)
            ->set('code', 'MALA')
            ->set('name', 'Mala')
            ->set('fields', [campo('firma', 'signature', ['reportable' => true])])
            ->call('publish')
            ->assertHasErrors('fields');

        expect(Template::query()->where('code', 'MALA')->exists())->toBeFalse();
    });
})->group('forms');

it('no crea la plantilla si el esquema no se sostiene', function (): void {
    enTenantDiseno(function (): void {
        comoDuena();

        Livewire::test(TemplateDesigner::class)
            ->set('code', 'DUPLICADA')
            ->set('name', 'Duplicada')
            ->set('fields', [campo('monto', 'money'), campo('monto', 'text')])
            ->call('publish')
            ->assertHasErrors('fields');

        // Que no quede una plantilla huerfana sin ninguna version.
        expect(Template::query()->where('code', 'DUPLICADA')->exists())->toBeFalse();
    });
})->group('forms');

it('exige una clave de campo con formato de identificador', function (): void {
    enTenantDiseno(function (): void {
        comoDuena();

        Livewire::test(TemplateDesigner::class)
            ->set('code', 'X')
            ->set('name', 'X')
            ->set('fields', [campo('Monto Total')])
            ->call('publish')
            ->assertHasErrors('fields.0.key');
    });
})->group('forms');

it('exige al menos un campo', function (): void {
    enTenantDiseno(function (): void {
        comoDuena();

        Livewire::test(TemplateDesigner::class)
            ->set('code', 'VACIA')
            ->set('name', 'Vacia')
            ->set('fields', [])
            ->call('publish')
            ->assertHasErrors('fields');
    });
})->group('forms');

it('reordena y quita campos', function (): void {
    enTenantDiseno(function (): void {
        comoDuena();

        Livewire::test(TemplateDesigner::class)
            ->set('fields', [campo('a'), campo('b'), campo('c')])
            ->call('moveDown', 0)
            ->assertSet('fields.0.key', 'b')
            ->assertSet('fields.1.key', 'a')
            ->call('removeField', 1)
            ->assertSet('fields.0.key', 'b')
            ->assertSet('fields.1.key', 'c')
            // Reindexado: sin esto quedaria un hueco y Livewire lo volveria un
            // objeto en vez de una lista.
            ->assertCount('fields', 2);
    });
})->group('forms');

it('no deja disenar a quien solo puede ver plantillas', function (): void {
    enTenantDiseno(function (): void {
        $supervisor = User::create([
            'name' => 'Supervisor',
            'email' => 'supervisor@acme.test',
            'password' => CLAVE_DISENO,
        ]);
        $supervisor->assignRole(RoleName::Supervisor->value);
        auth()->login($supervisor->fresh());

        // Ve el listado...
        Livewire::test(TemplateList::class)->assertOk();

        // ...pero no disena.
        Livewire::test(TemplateDesigner::class)->assertForbidden();
    });
})->group('forms');

it('separa disenar de publicar', function (): void {
    enTenantDiseno(function (): void {
        // Publicar es lo que pone la plantilla a exigir entregas, asi que tiene
        // permiso propio: se puede confiar el diseno sin confiar la activacion.
        $disenadora = User::create([
            'name' => 'Disenadora',
            'email' => 'diseno@acme.test',
            'password' => CLAVE_DISENO,
        ]);
        $disenadora->givePermissionTo([
            PermissionName::TemplateView->value,
            PermissionName::TemplateManage->value,
        ]);
        auth()->login($disenadora->fresh());

        // Entra al disenador sin problema.
        $componente = Livewire::test(TemplateDesigner::class)->assertOk();

        // Pero no puede publicar.
        $componente
            ->set('code', 'SINPERMISO')
            ->set('name', 'Sin permiso')
            ->set('fields', [campo('monto', 'money')])
            ->call('publish')
            ->assertForbidden();

        expect(Template::query()->where('code', 'SINPERMISO')->exists())->toBeFalse();
    });
})->group('forms');
