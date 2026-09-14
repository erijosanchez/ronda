<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronda\Directory\Presentation\Livewire\SiteForm;
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
Route::get('/sedes/nueva', SiteForm::class)->name('sites.create');

// El binding resuelve la sede con el scope de frontera puesto: quien no la
// tiene asignada recibe un 404 antes de que corra nada.
Route::get('/sedes/{site}/editar', SiteForm::class)->name('sites.edit');
