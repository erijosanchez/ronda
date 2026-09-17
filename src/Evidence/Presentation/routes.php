<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronda\Evidence\Presentation\Http\EvidenceController;

/*
| Rutas del modulo Evidence.
|
| Se incluyen desde routes/tenant.php, ya dentro del grupo que identifica el
| tenant por dominio y exige sesion. Esta ademas exige firma: solo se llega con
| una URL emitida por SignedEvidenceUrlQuery (ADR 0009).
|
| Rutas en espanol y en kebab-case (CLAUDE.md).
*/

Route::get('/evidencia/{attachment}', EvidenceController::class)
    ->middleware('signed')
    ->name('evidence.show');
