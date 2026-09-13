<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronda\Insights\Presentation\Livewire\Dashboard;

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
