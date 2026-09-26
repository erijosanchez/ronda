<?php

declare(strict_types=1);

use Database\Seeders\PlanSeeder;
use Ronda\Platform\Domain\Models\Plan;
use Ronda\Platform\Domain\PlanCode;

// El sitio publico. RONDA-PLAN-MAESTRO.md sec. 15.3 y 3.6
//
// Lo que se prueba no es que las paginas carguen, sino que el precio que se
// PUBLICA salga de la misma tabla que el que se COBRA. Un precio escrito en una
// vista se separa del real el dia que alguien cambie la tarifa, y esa diferencia
// se descubre cuando un cliente reclama.

beforeEach(function (): void {
    $this->seed(PlanSeeder::class);
});

it('publica los precios que hay en la base', function (): void {
    $starter = Plan::query()->where('code', PlanCode::Starter->value)->firstOrFail();

    $this->get('/precios')
        ->assertOk()
        ->assertSee($starter->name)
        ->assertSee($starter->currency.' '.$starter->price_per_site)
        ->assertSee(__('Minimum :count branches', ['count' => $starter->min_sites]));
});

it('cambia el precio de la pagina cuando cambia el de la tabla', function (): void {
    // La prueba de que es un dato y no codigo: se sube la tarifa y la pagina lo
    // dice, sin tocar una linea.
    Plan::query()->where('code', PlanCode::Starter->value)->update(['price_per_site' => '35.00']);

    $this->get('/precios')
        ->assertOk()
        ->assertSee('PEN 35.00')
        ->assertDontSee('PEN 29.00');
});

it('no publica el plan cotizado', function (): void {
    $enterprise = Plan::query()->where('code', PlanCode::Enterprise->value)->firstOrFail();

    // Enterprise no tiene precio de lista: ensenar uno seria inventarselo.
    $this->get('/precios')
        ->assertOk()
        ->assertDontSee($enterprise->name)
        // Pero si se dice que existe y que se cotiza.
        ->assertSee(__('Bigger, or with something of your own?'));
});

it('lleva al registro desde la portada y desde los precios', function (): void {
    $this->get('/')->assertOk()->assertSee(route('register.tenant'));
    $this->get('/precios')->assertOk()->assertSee(route('register.tenant'));
});

it('dice cuantos dias dura la prueba, leidos de la configuracion', function (): void {
    config()->set('billing.trial_days', 21);

    $this->get('/')
        ->assertOk()
        ->assertSee(__('Start :days free days', ['days' => 21]));
});

it('no ofrece entrar desde el dominio central', function (): void {
    // La sesion se abre en la direccion de cada cliente. Un enlace a /login
    // aqui llevaria a un 404, que es la peor forma de recibir a alguien.
    $this->get('/')
        ->assertOk()
        ->assertDontSee('href="'.url('/login').'"', escape: false);
});
