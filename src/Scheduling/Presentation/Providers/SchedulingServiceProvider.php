<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Presentation\Providers;

use Illuminate\Support\ServiceProvider;
use Ronda\Scheduling\Presentation\Console\MaterializeObligationsCommand;

/**
 * Registra lo que el modulo Scheduling aporta al contenedor.
 */
final class SchedulingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([MaterializeObligationsCommand::class]);
        }
    }
}
