<?php

declare(strict_types=1);

namespace Ronda\Directory\Presentation\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Ronda\Directory\Presentation\Livewire\SiteForm;
use Ronda\Directory\Presentation\Livewire\SiteList;

/**
 * Registra lo que el modulo Directory aporta al contenedor.
 *
 * Las rutas NO se cargan aqui: van en routes/tenant.php, dentro del grupo que
 * identifica el tenant por dominio y exige sesion.
 */
final class DirectoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'directory');

        Livewire::component('directory.site-list', SiteList::class);
        Livewire::component('directory.site-form', SiteForm::class);
    }
}
