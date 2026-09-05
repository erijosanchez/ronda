<?php

declare(strict_types=1);

use Laravel\Fortify\Features;
use Ronda\Identity\Domain\Models\User;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;

// Autenticacion. RONDA-PLAN-MAESTRO.md sec. 10.2
//
// Corre contra un tenant provisionado de verdad porque el login solo existe en
// el dominio del cliente: en el central devuelve 404. Una prueba que montase
// el usuario en la base central habria pasado sin ejercitar nada de eso.

const CLAVE = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: CLAVE,
    ));

    $this->url = 'http://acme.ronda.test';
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

it('muestra el formulario de acceso en el dominio del tenant', function (): void {
    $this->get($this->url.'/login')
        ->assertOk()
        ->assertSee(__('Log in'));
})->group('auth');

it('no expone el acceso en el dominio central', function (): void {
    // PreventAccessFromCentralDomains. Que la portada del SaaS ofrezca un
    // formulario que autentica contra una base sin usuarios es como se acaba
    // con un 500 en produccion.
    $this->get('http://localhost/login')->assertNotFound();
})->group('auth');

it('deja entrar con las credenciales correctas', function (): void {
    $this->post($this->url.'/login', [
        'email' => 'owner@acme.test',
        'password' => CLAVE,
    ])->assertRedirect(config('fortify.home'));

    $this->assertAuthenticated();
})->group('auth');

it('rechaza una contrasena incorrecta sin decir si el correo existe', function (): void {
    $respuesta = $this->from($this->url.'/login')->post($this->url.'/login', [
        'email' => 'owner@acme.test',
        'password' => 'esta-no-es',
    ]);

    $respuesta->assertRedirect($this->url.'/login');
    $this->assertGuest();

    // El mismo mensaje para correo inexistente y contrasena mala: distinguirlos
    // permite enumerar usuarios del cliente.
    expect(session('errors')->first('email'))->toBe(__('auth.failed'));
})->group('auth');

it('da el mismo mensaje cuando el correo no existe', function (): void {
    $this->from($this->url.'/login')->post($this->url.'/login', [
        'email' => 'nadie@acme.test',
        'password' => CLAVE,
    ]);

    $this->assertGuest();
    expect(session('errors')->first('email'))->toBe(__('auth.failed'));
})->group('auth');

it('bloquea tras cinco intentos fallidos', function (): void {
    // Limite por cuenta Y por IP (sec. 10.2). Cinco intentos por minuto.
    foreach (range(1, 5) as $intento) {
        $this->from($this->url.'/login')->post($this->url.'/login', [
            'email' => 'owner@acme.test',
            'password' => 'incorrecta-'.$intento,
        ]);
    }

    $this->from($this->url.'/login')->post($this->url.'/login', [
        'email' => 'owner@acme.test',
        'password' => CLAVE,   // ahora la correcta: debe rechazarla igual
    ]);

    // El sexto intento se bloquea aunque la contrasena sea la correcta.
    $this->assertGuest();
    expect(session('errors')->first('email'))->toContain(__('auth.throttle', ['seconds' => 60, 'minutes' => 1]));
})->group('auth');

it('manda al reto de 2FA cuando el usuario lo tiene confirmado', function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;

    $tenant->run(function (): void {
        User::query()->where('email', 'owner@acme.test')->update([
            'two_factor_secret' => encrypt('secreto-totp-de-prueba'),
            'two_factor_recovery_codes' => encrypt(json_encode(['codigo-uno'])),
            'two_factor_confirmed_at' => now(),
        ]);
    });

    $this->post($this->url.'/login', [
        'email' => 'owner@acme.test',
        'password' => CLAVE,
    ])->assertRedirect($this->url.'/two-factor-challenge');

    // Contrasena correcta pero sesion aun sin abrir: falta el segundo factor.
    $this->assertGuest();
})->group('auth');

it('tiene activada la feature de 2FA', function (): void {
    // Obligatorio para owner y admin (sec. 10.2). Si alguien la comenta en
    // config/fortify.php, este test lo detiene.
    expect(Features::enabled(Features::twoFactorAuthentication()))->toBeTrue();
})->group('auth');

it('cierra la sesion', function (): void {
    $this->post($this->url.'/login', ['email' => 'owner@acme.test', 'password' => CLAVE]);
    $this->assertAuthenticated();

    $this->post($this->url.'/logout')->assertRedirect();
    $this->assertGuest();
})->group('auth');
