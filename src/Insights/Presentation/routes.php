<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronda\Insights\Presentation\Http\ExportDownloadController;
use Ronda\Insights\Presentation\Livewire\Dashboard;
use Ronda\Insights\Presentation\Livewire\ExportList;

/*
| Rutas del modulo Insights.
|
| Se incluyen desde routes/tenant.php, ya dentro del grupo que identifica el
| tenant por dominio y exige sesion. Este archivo no declara middleware propio:
| si lo hiciera, habria dos sitios que decidir quien entra.
|
| Las rutas van en espanol y en kebab-case (CLAUDE.md).
*/

Route::get('/panel', Dashboard::class)->name('panel');

// Exportaciones (sec. 13): se encargan aqui y las escribe un job. El archivo
// sale por la aplicacion, nunca por una URL del bucket.
Route::get('/exportaciones', ExportList::class)->name('exports.index');
Route::get('/exportaciones/{export}/descargar', ExportDownloadController::class)->name('exports.download');
