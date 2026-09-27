<?php

declare(strict_types=1);

namespace Ronda\Api\Presentation\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Ronda\Api\Presentation\Livewire\ApiTokens;

/**
 * Registra lo que el modulo Api aporta. RONDA-PLAN-MAESTRO.md sec. 13.1
 *
 * Las rutas NO se cargan aqui: van en routes/tenant.php, en un grupo propio,
 * sin sesion ni CSRF.
 *
 * La tabla `personal_access_tokens` tampoco se registra aqui: esta version de
 * Sanctum solo OFRECE su migracion para publicarla, y la copia de Ronda vive
 * en `database/migrations/tenant`, porque un token pertenece a una persona y
 * las personas viven en la base de su cliente (ADR 0002).
 */
final class ApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'api');

        Livewire::component('api.tokens', ApiTokens::class);
    }
}
