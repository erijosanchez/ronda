<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Telescope queda fuera del auto-discovery (composer.json
        // extra.laravel.dont-discover) y se registra SOLO en local.
        //
        // Con el descubrimiento automatico se activaba en cualquier entorno y
        // escribia en `telescope_entries`; sin esa tabla, cada peticion
        // lanzaba una excepcion. Ver docs/HANDOFF.md
        //
        // Para usarlo: php artisan telescope:install && php artisan migrate
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        // El N+1 debe fallar ruidosamente antes de llegar a produccion.
        // Ver RONDA-PLAN-MAESTRO.md sec. 14
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
