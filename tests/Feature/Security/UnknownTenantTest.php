<?php

declare(strict_types=1);

// Un subdominio que no es de nadie.
//
// En reports-trimax cualquier error acababa en una pantalla de error del
// servidor; aqui una direccion mal escrita es lo mas comun que va a pasar —el
// encargado escribe «acmee» en vez de «acme»— y tiene que responder como lo
// que es: no existe.
//
// Ademas de la cortesia hay una razon operativa: la pagina de error con
// depuracion activa tarda mas de lo que PHP permite y deja al worker de Octane
// bloqueado treinta segundos. Una direccion mal escrita bastaba para dejar al
// servidor sin atender a nadie.

it('responde 404 a un subdominio que no existe, no un error del servidor', function (): void {
    $this->get('http://nadie.localhost/panel')->assertNotFound();
})->group('security');

it('muestra una pagina propia y no una traza de error', function (): void {
    // Lo que ve quien escribio mal la direccion tiene que decirle que hacer.
    // Una traza de PHP, ademas de no ayudarle, ensena rutas del servidor.
    $respuesta = $this->get('http://nadie.localhost/panel');

    $respuesta->assertNotFound()
        ->assertSee(__('This address does not exist'))
        ->assertDontSee('Stancl')
        ->assertDontSee('/app/vendor');
})->group('security');
