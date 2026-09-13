<?php

declare(strict_types=1);

use Ronda\Identity\Domain\RoleName;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;

// El panel: donde aterriza quien inicia sesion.
//
// Antes de esto el login redirigia a /home, que no existia: se entraba y se
// caia en un 404. La prueba cubre el recorrido entero, no solo la ruta.

const PANEL_CLAVE = 'una-contrasena-larga-de-prueba';

beforeEach(function (): void {
    $this->tenant = resolve(CreateTenant::class)(new CreateTenantData(
        name: 'Acme',
        slug: 'acme',
        domain: 'acme.ronda.test',
        ownerName: 'Duena de Acme',
        ownerEmail: 'owner@acme.test',
        ownerPassword: PANEL_CLAVE,
    ));

    $this->url = 'http://acme.ronda.test';
});

afterEach(function (): void {
    /** @var Tenant $tenant */
    $tenant = $this->tenant;
    dropTenantDatabase($tenant);
});

function entrar(): void
{
    test()->post(test()->url.'/login', [
        'email' => 'owner@acme.test',
        'password' => PANEL_CLAVE,
    ]);
}

it('manda al login a quien no ha iniciado sesion', function (): void {
    $this->get($this->url.'/panel')->assertRedirect($this->url.'/login');
})->group('auth');

it('lleva al panel tras iniciar sesion', function (): void {
    // `fortify.home` apunta aqui. Si alguien lo cambia a una ruta que no
    // existe, esta prueba lo detecta antes que un usuario.
    $this->post($this->url.'/login', [
        'email' => 'owner@acme.test',
        'password' => PANEL_CLAVE,
    ])->assertRedirect($this->url.'/panel');

    $this->get($this->url.'/panel')->assertOk();
})->group('auth');

it('saluda al usuario y dice en que cliente esta', function (): void {
    entrar();

    $this->get($this->url.'/panel')
        ->assertOk()
        ->assertSee('Duena de Acme')
        ->assertSee('Acme');
})->group('auth');

it('muestra los roles con su nombre traducido', function (): void {
    entrar();

    // El rol se guarda como `owner` y se muestra como «Propietario»: codigo en
    // ingles, interfaz en espanol.
    $this->get($this->url.'/panel')
        ->assertOk()
        ->assertSee(RoleName::Owner->label())
        ->assertDontSee('>owner<', escape: false);
})->group('auth');

it('no existe en el dominio central', function (): void {
    $this->get('http://localhost/panel')->assertNotFound();
})->group('auth');

it('deja cerrar sesion desde el panel', function (): void {
    entrar();
    $this->assertAuthenticated();

    $this->post($this->url.'/logout')->assertRedirect();
    $this->assertGuest();

    $this->get($this->url.'/panel')->assertRedirect($this->url.'/login');
})->group('auth');
