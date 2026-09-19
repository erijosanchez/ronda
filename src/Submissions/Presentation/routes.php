<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronda\Submissions\Presentation\Http\OutboxSubmitController;
use Ronda\Submissions\Presentation\Livewire\PendingObligations;
use Ronda\Submissions\Presentation\Livewire\SubmissionDetail;
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

// La puerta de la cola de envio del telefono (sec. 13.3): lo que se lleno sin
// senal entra por aqui cuando vuelve la red. Misma Policy que el formulario.
Route::post('/pendientes/{obligation}/entregar', OutboxSubmitController::class)->name('submissions.store');

// SubmissionPolicy decide: permiso de ver envios (o ser su autor) y alcanzar
// la sede.
Route::get('/envios/{submission}', SubmissionDetail::class)->name('submissions.show');
