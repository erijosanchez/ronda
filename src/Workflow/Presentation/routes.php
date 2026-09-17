<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronda\Workflow\Presentation\Livewire\ReviewInbox;
use Ronda\Workflow\Presentation\Livewire\SubmissionCorrectionForm;

/*
| Rutas del modulo Workflow.
|
| Se incluyen desde routes/tenant.php, ya dentro del grupo que identifica el
| tenant por dominio y exige sesion.
|
| Rutas en espanol y en kebab-case (CLAUDE.md).
*/

Route::get('/revision', ReviewInbox::class)->name('reviews.index');

// Cuelga del envio: se llega desde su ficha.
Route::get('/envios/{submission}/corregir', SubmissionCorrectionForm::class)->name('submissions.correct');
