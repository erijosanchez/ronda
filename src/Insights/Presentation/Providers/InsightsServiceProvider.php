<?php

declare(strict_types=1);

namespace Ronda\Insights\Presentation\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Ronda\Insights\Presentation\Console\RecalculateKpisCommand;
use Ronda\Insights\Presentation\Livewire\Dashboard;

/**
 * Registra lo que el modulo Insights aporta al contenedor.
 *
 * Hace falta uno por modulo porque nada de esto se descubre solo: las vistas
 * viven en src/ y no en resources/, y Livewire solo autodescubre componentes
 * bajo App\Livewire.
 *
 * Las rutas NO se cargan aqui: van en routes/tenant.php, dentro del grupo que
 * identifica el tenant por dominio. Registrarlas desde el proveedor las
 * dejaria fuera de ese grupo.
 */
final class InsightsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'insights');

        Livewire::component('insights.dashboard', Dashboard::class);

        if ($this->app->runningInConsole()) {
            $this->commands([RecalculateKpisCommand::class]);
        }
    }
}
