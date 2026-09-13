<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronda\Directory\Presentation\Livewire\SiteList;

/*
| Rutas del modulo Directory.
|
| Se incluyen desde routes/tenant.php, ya dentro del grupo que identifica el
| tenant por dominio y exige sesion. Este archivo no declara middleware propio.
|
| Rutas en espanol y en kebab-case (CLAUDE.md).
*/

Route::get('/sedes', SiteList::class)->name('sites.index');
