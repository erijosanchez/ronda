<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronda\Submissions\Presentation\Livewire\PendingObligations;
use Ronda\Submissions\Presentation\Livewire\SubmissionForm;

/*
| Rutas del modulo Submissions.
|
| Se incluyen desde routes/tenant.php, ya dentro del grupo que identifica el
| tenant por dominio y exige sesion.
|
| Rutas en espanol y en kebab-case (CLAUDE.md).
*/

Route::get('/pendientes', PendingObligations::class)->name('submissions.pending');
Route::get('/pendientes/{obligation}', SubmissionForm::class)->name('submissions.create');
