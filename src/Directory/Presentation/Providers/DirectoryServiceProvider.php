<?php

declare(strict_types=1);

namespace Ronda\Directory\Presentation\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Ronda\Directory\Presentation\Livewire\PositionList;
use Ronda\Directory\Presentation\Livewire\SiteForm;
use Ronda\Directory\Presentation\Livewire\SiteList;
use Ronda\Directory\Presentation\Livewire\UserSiteAssignments;
use Ronda\Directory\Presentation\Livewire\ZoneForm;
use Ronda\Directory\Presentation\Livewire\ZoneList;

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
        Livewire::component('directory.user-site-assignments', UserSiteAssignments::class);
        Livewire::component('directory.zone-list', ZoneList::class);
        Livewire::component('directory.zone-form', ZoneForm::class);
        Livewire::component('directory.position-list', PositionList::class);
    }
}
