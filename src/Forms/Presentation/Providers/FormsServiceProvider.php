<?php

declare(strict_types=1);

namespace Ronda\Forms\Presentation\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Ronda\Forms\Presentation\Livewire\TemplateCatalog;
use Ronda\Forms\Presentation\Livewire\TemplateDesigner;
use Ronda\Forms\Presentation\Livewire\TemplateList;

/**
 * Registra lo que el modulo Forms aporta al contenedor.
 */
final class FormsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'forms');

        Livewire::component('forms.template-list', TemplateList::class);
        Livewire::component('forms.template-designer', TemplateDesigner::class);
        Livewire::component('forms.template-catalog', TemplateCatalog::class);
    }
}
