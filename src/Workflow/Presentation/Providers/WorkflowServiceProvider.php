<?php

declare(strict_types=1);

namespace Ronda\Workflow\Presentation\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Ronda\Workflow\Presentation\Livewire\ReviewInbox;
use Ronda\Workflow\Presentation\Livewire\ReviewPanel;
use Ronda\Workflow\Presentation\Livewire\SubmissionCorrectionForm;

/**
 * Registra lo que el modulo Workflow aporta al contenedor.
 *
 * Las rutas NO se cargan aqui: van en routes/tenant.php, dentro del grupo que
 * identifica el tenant por dominio y exige sesion.
 */
final class WorkflowServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'workflow');

        Livewire::component('workflow.review-inbox', ReviewInbox::class);
        Livewire::component('workflow.review-panel', ReviewPanel::class);
        Livewire::component('workflow.submission-correction-form', SubmissionCorrectionForm::class);
    }
}
