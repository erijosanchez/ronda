<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Presentation\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Ronda\Scheduling\Presentation\Console\MaterializeObligationsCommand;
use Ronda\Scheduling\Presentation\Livewire\ScheduleForm;
use Ronda\Scheduling\Presentation\Livewire\ScheduleList;

/**
 * Registra lo que el modulo Scheduling aporta al contenedor.
 *
 * Las rutas NO se cargan aqui: van en routes/tenant.php, dentro del grupo que
 * identifica el tenant por dominio y exige sesion.
 */
final class SchedulingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'scheduling');

        Livewire::component('scheduling.schedule-list', ScheduleList::class);
        Livewire::component('scheduling.schedule-form', ScheduleForm::class);

        if ($this->app->runningInConsole()) {
            $this->commands([MaterializeObligationsCommand::class]);
        }
    }
}
