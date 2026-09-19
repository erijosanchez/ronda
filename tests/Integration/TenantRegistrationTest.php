<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Application\Jobs\ProvisionTenantJob;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Platform\Presentation\Livewire\RegisterForm;
use Ronda\Platform\Presentation\Livewire\TenantProvisioning;
use Stancl\Tenancy\Database\Models\Domain;

// Registro self-service. RONDA-PLAN-MAESTRO.md sec. 15.3
//
// Es la unica puerta del producto que esta abierta a cualquiera: lo que se
// prueba aqui es que detras de ella no se puede pedir un subdominio nuestro,
// ni el de otro, ni crear cuentas en bucle, y que nadie acaba en un dominio
// que todavia no existe.

/**
 * Los campos completos de un registro valido. Cada prueba cambia lo suyo.
 *
 * @param  array<string, string|bool>  $cambios
 * @return array<string, string|bool>
 */
function registroValido(array $cambios = []): array
{
    return array_merge([
        'company' => 'Panaderia Delicia',
        'subdomain' => 'delicia',
        'ownerName' => 'Rosa Quispe',
        'email' => 'rosa@delicia.pe',
        'password' => 'una-contrasena-larga-de-prueba',
        'password_confirmation' => 'una-contrasena-larga-de-prueba',
        'terms' => true,
    ], $cambios);
}

/**
 * Envia el formulario sin provisionar de verdad: crear una base de datos por
 * prueba costaria minutos y no es lo que estas pruebas miran.
 *
 * @param  array<string, string|bool>  $cambios
 */
function enviarRegistro(array $cambios = []): Testable
{
    Queue::fake();

    return Livewire::test(RegisterForm::class)
        ->set(registroValido($cambios))
        ->call('register');
}

it('da de alta al cliente y lo manda a esperar su cuenta', function (): void {
    $componente = enviarRegistro();

    $tenant = Tenant::query()->where('slug', 'delicia')->first();

    expect($tenant)->not->toBeNull()
        ->and($tenant->name)->toBe('Panaderia Delicia')
        // Todavia no esta listo: la provision corre despues, en la cola.
        ->and($tenant->isReady())->toBeFalse();

    expect(Domain::query()->where('tenant_id', $tenant->id)->value('domain'))
        ->toBe('delicia.'.config('platform.domain'));

    Queue::assertPushed(ProvisionTenantJob::class);

    $componente->assertRedirect(route('register.waiting', ['tenant' => $tenant->id], absolute: false));
})->group('tenancy');

it('no entrega un subdominio reservado', function (): void {
    enviarRegistro(['subdomain' => 'www'])
        ->assertHasErrors('subdomain');

    expect(Tenant::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->group('tenancy');

it('no entrega un subdominio que ya se llevo otro', function (): void {
    resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Delicia',
        slug: 'delicia',
        domain: 'delicia.'.config('platform.domain'),
        ownerName: 'Otra persona',
        ownerEmail: 'otra@delicia.pe',
        ownerPassword: 'una-contrasena-larga-de-prueba',
    ));

    enviarRegistro()->assertHasErrors('subdomain');

    // El alta previa sigue siendo la unica.
    expect(Tenant::query()->where('slug', 'delicia')->count())->toBe(1);
})->group('tenancy');

it('rechaza los registros incompletos o mal formados', function (array $cambios, string $campo): void {
    enviarRegistro($cambios)->assertHasErrors($campo);

    expect(Tenant::query()->count())->toBe(0);
})->with([
    'sin nombre' => [['company' => ''], 'company'],
    'subdominio corto' => [['subdomain' => 'ab'], 'subdomain'],
    'contrasena corta' => [['password' => 'corta1', 'password_confirmation' => 'corta1'], 'password'],
    'contrasenas distintas' => [['password_confirmation' => 'otra-cosa-larga'], 'password'],
    'correo invalido' => [['email' => 'no-es-un-correo'], 'email'],
    'sin aceptar terminos' => [['terms' => false], 'terms'],
])->group('tenancy');

it('corta el alta en bucle desde la misma IP', function (): void {
    config()->set('platform.registration.per_hour', 2);

    Queue::fake();

    foreach (['uno', 'dos'] as $slug) {
        Livewire::test(RegisterForm::class)
            ->set(registroValido(['subdomain' => $slug, 'email' => $slug.'@delicia.pe']))
            ->call('register')
            ->assertHasNoErrors();
    }

    Livewire::test(RegisterForm::class)
        ->set(registroValido(['subdomain' => 'tres', 'email' => 'tres@delicia.pe']))
        ->call('register')
        ->assertHasErrors('company');

    expect(Tenant::query()->count())->toBe(2);
})->group('tenancy');

it('sugiere la direccion mientras nadie la escriba', function (): void {
    Livewire::test(RegisterForm::class)
        ->set('company', 'Panaderia Delicia')
        ->assertSet('subdomain', 'panaderia-delicia')
        // En cuanto la editan, deja de moverse sola.
        ->set('subdomain', 'Delicia Centro')
        ->assertSet('subdomain', 'delicia-centro')
        ->set('company', 'Otro Nombre')
        ->assertSet('subdomain', 'delicia-centro');
})->group('tenancy');

it('cierra el registro cuando esta apagado', function (): void {
    config()->set('platform.registration.enabled', false);

    Livewire::test(RegisterForm::class)->assertStatus(404);
})->group('tenancy');

it('no manda a nadie a su dominio hasta que la cuenta existe', function (): void {
    Queue::fake();

    $tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Delicia',
        slug: 'delicia',
        domain: 'delicia.'.config('platform.domain'),
        ownerName: 'Rosa Quispe',
        ownerEmail: 'rosa@delicia.pe',
        ownerPassword: 'una-contrasena-larga-de-prueba',
    ));

    Livewire::test(TenantProvisioning::class, ['tenant' => $tenant])
        ->assertSee(__('Preparing your account'))
        ->assertDontSee(__('Go in'));

    $tenant->forceFill(['provisioned_at' => now()])->save();

    Livewire::test(TenantProvisioning::class, ['tenant' => $tenant])
        ->assertSee(__('Your account is ready'))
        // El enlace lleva al dominio del cliente, no al central.
        ->assertSee('delicia.'.config('platform.domain'));
})->group('tenancy');

it('marca la cuenta como lista al terminar la provision', function (): void {
    // Esta si provisiona de verdad: lo que se comprueba es que el oyente
    // escribe en la base CENTRAL, que es donde mira la pantalla de espera.
    $tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Delicia',
        slug: 'delicia',
        domain: 'delicia.'.config('platform.domain'),
        ownerName: 'Rosa Quispe',
        ownerEmail: 'rosa@delicia.pe',
        ownerPassword: 'una-contrasena-larga-de-prueba',
    ));

    expect($tenant->fresh()?->isReady())->toBeTrue();

    dropTenantDatabase($tenant);
})->group('tenancy');
