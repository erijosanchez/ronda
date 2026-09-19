<?php

declare(strict_types=1);

namespace Ronda\Platform\Presentation\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Ronda\Platform\Presentation\Console\RenewSubscriptionsCommand;
use Ronda\Platform\Presentation\Livewire\PlanAndUsage;
use Ronda\Platform\Presentation\Livewire\RegisterForm;
use Ronda\Platform\Presentation\Livewire\StartWizard;
use Ronda\Platform\Presentation\Livewire\TenantProvisioning;

/**
 * Registra lo que el modulo Platform aporta a la interfaz.
 *
 * Sus rutas son las unicas que NO viven en routes/tenant.php: el registro
 * ocurre en el dominio central, antes de que exista cliente al que identificar.
 */
final class PlatformServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'platform');

        Livewire::component('platform.register-form', RegisterForm::class);
        Livewire::component('platform.tenant-provisioning', TenantProvisioning::class);
        Livewire::component('platform.start-wizard', StartWizard::class);
        Livewire::component('platform.plan-and-usage', PlanAndUsage::class);

        if ($this->app->runningInConsole()) {
            $this->commands([RenewSubscriptionsCommand::class]);
        }
    }
}
