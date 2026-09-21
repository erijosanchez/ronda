<?php

declare(strict_types=1);

use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Identity\Domain\Models\User;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\PlatformUser;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Platform\Presentation\Livewire\BackOfficeLogin;
use Ronda\Platform\Presentation\Livewire\BackOfficeTenant;
use Ronda\Platform\Presentation\Livewire\BackOfficeTenants;

// Back-office de Ronda. RONDA-PLAN-MAESTRO.md sec. 15.4
//
// Lo que se prueba es la puerta, que es lo unico que separa a un desconocido de
// la operacion de TODOS los clientes: que el segundo factor no se pueda saltar,
// que una cuenta dada de baja deje de entrar en el acto, y que un usuario de
// cliente no sea usuario del back-office.

const CLAVE_SOPORTE = 'una-contrasena-larga-de-soporte';

beforeEach(function (): void {
    // `refresh()` a proposito: el modelo recien creado no trae las columnas
    // con valor por defecto —`remember_token` entre ellas— y cerrar sesion las
    // toca. En la vida real el usuario siempre llega leido de la base.
    $this->soporte = PlatformUser::query()->create([
        'name' => 'Soporte Ronda',
        'email' => 'soporte@ronda.test',
        'password' => CLAVE_SOPORTE,
        'is_active' => true,
    ])->refresh();

    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE_SOPORTE,
    ));
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

/**
 * Deja la cuenta con segundo factor ya configurado y devuelve un codigo valido.
 */
function conSegundoFactor(PlatformUser $user): string
{
    $totp = resolve(TwoFactorAuthenticationProvider::class);
    $secreto = $totp->generateSecretKey();

    $user->forceFill([
        'two_factor_secret' => $secreto,
        'two_factor_confirmed_at' => now(),
    ])->save();

    return (new Google2FA)->getCurrentOtp($secreto);
}

it('no deja entrar con la contrasena sola: exige el segundo factor', function (): void {
    /** @var PlatformUser $soporte */
    $soporte = $this->soporte;
    conSegundoFactor($soporte);

    Livewire::test(BackOfficeLogin::class)
        ->set('email', $soporte->email)
        ->set('password', CLAVE_SOPORTE)
        ->call('submit')
        ->assertHasNoErrors()
        // No entra: queda en el paso del codigo.
        ->assertSet('step', 'codigo')
        ->assertNoRedirect();

    expect(auth('platform')->check())->toBeFalse();
})->group('security');

it('entra cuando el codigo es correcto', function (): void {
    /** @var PlatformUser $soporte */
    $soporte = $this->soporte;
    $codigo = conSegundoFactor($soporte);

    Livewire::test(BackOfficeLogin::class)
        ->set('email', $soporte->email)
        ->set('password', CLAVE_SOPORTE)
        ->call('submit')
        ->set('code', $codigo)
        ->call('challenge')
        ->assertRedirect(route('back-office.tenants'));

    expect(auth('platform')->id())->toBe($soporte->getKey())
        ->and($soporte->fresh()?->last_login_at)->not->toBeNull();
})->group('security');

it('obliga a configurar el segundo factor a quien no lo tiene', function (): void {
    /** @var PlatformUser $soporte */
    $soporte = $this->soporte;

    Livewire::test(BackOfficeLogin::class)
        ->set('email', $soporte->email)
        ->set('password', CLAVE_SOPORTE)
        ->call('submit')
        ->assertSet('step', 'configurar');

    expect(auth('platform')->check())->toBeFalse()
        // Se guardo el secreto pero SIN confirmar: la cuenta sigue sin poder
        // entrar hasta que teclee un codigo de su aplicacion.
        ->and($soporte->fresh()?->two_factor_secret)->not->toBeNull()
        ->and($soporte->fresh()?->two_factor_confirmed_at)->toBeNull()
        ->and($soporte->fresh()?->canUseBackOffice())->toBeFalse();
})->group('security');

it('no dice si el correo existe cuando la contrasena esta mal', function (): void {
    /** @var PlatformUser $soporte */
    $soporte = $this->soporte;

    $conCuenta = Livewire::test(BackOfficeLogin::class)
        ->set('email', $soporte->email)
        ->set('password', 'otra-cosa')
        ->call('submit');

    $sinCuenta = Livewire::test(BackOfficeLogin::class)
        ->set('email', 'nadie@ronda.test')
        ->set('password', 'otra-cosa')
        ->call('submit');

    expect($conCuenta->errors()->first('email'))
        ->toBe($sinCuenta->errors()->first('email'));
})->group('security');

it('echa en el acto a una cuenta dada de baja', function (): void {
    /** @var PlatformUser $soporte */
    $soporte = $this->soporte;
    conSegundoFactor($soporte);

    $this->actingAs($soporte, 'platform')
        ->get(route('back-office.tenants'))
        ->assertOk();

    // Dar de baja no espera a que cierre el navegador.
    $soporte->forceFill(['is_active' => false])->save();

    $this->actingAs($soporte, 'platform')
        ->get(route('back-office.tenants'))
        ->assertRedirect(route('back-office.login'));
})->group('security');

it('no deja entrar al back-office a un usuario de cliente', function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;

    $usuario = $tenant->run(
        static fn () => User::query()->where('email', 'owner@acme.test')->firstOrFail(),
    );

    // Ni siquiera es del mismo guard: su sesion no vale aqui.
    $this->actingAs($usuario)
        ->get(route('back-office.tenants'))
        ->assertRedirect(route('back-office.login'));
})->group('security');

it('lista los clientes y su estado', function (): void {
    /** @var PlatformUser $soporte */
    $soporte = $this->soporte;
    conSegundoFactor($soporte);

    $this->actingAs($soporte, 'platform');

    Livewire::test(BackOfficeTenants::class)
        ->assertSee('Acme')
        ->assertSee('acme.ronda.test')
        ->set('search', 'acme')
        ->assertSee('Acme')
        ->set('search', 'no-existe')
        ->assertSee(__('No clients match.'));
})->group('tenancy');

it('cuenta la operacion del cliente en su ficha, sin leer ningun reporte', function (): void {
    /** @var PlatformUser $soporte */
    $soporte = $this->soporte;
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    conSegundoFactor($soporte);

    $tenant->run(function (): void {
        Site::factory()->count(3)->create();
    });

    $this->actingAs($soporte, 'platform');

    // `fresh()` a proposito: la provision corre en un job, y la cola sync
    // serializa el job, asi que quien marca `provisioned_at` lo hace sobre una
    // COPIA. El objeto que tiene la prueba en memoria no se entera. En la
    // aplicacion no ocurre: la ruta resuelve el cliente leyendolo de la base.
    Livewire::test(BackOfficeTenant::class, ['tenant' => $tenant->fresh()])
        ->assertSee($tenant->name)
        ->assertSee(__('Operation'))
        // Tres sedes, y una sola persona: la duena.
        ->assertSee(__('Branches'))
        ->assertSee(__('No report submitted yet.'))
        // Y nada del contenido de ningun reporte.
        ->assertDontSee(__('Reports').':');
})->group('tenancy');
