<?php

declare(strict_types=1);

namespace Ronda\Submissions\Presentation\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Ronda\Submissions\Presentation\Livewire\PendingObligations;
use Ronda\Submissions\Presentation\Livewire\SubmissionForm;

/**
 * Registra lo que el modulo Submissions aporta al contenedor.
 */
final class SubmissionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'submissions');

        Livewire::component('submissions.pending-obligations', PendingObligations::class);
        Livewire::component('submissions.submission-form', SubmissionForm::class);
    }
}
