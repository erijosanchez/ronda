<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronda\Directory\Presentation\Livewire\PositionList;
use Ronda\Directory\Presentation\Livewire\SiteForm;
use Ronda\Directory\Presentation\Livewire\SiteList;
use Ronda\Directory\Presentation\Livewire\UserSiteAssignments;
use Ronda\Directory\Presentation\Livewire\ZoneForm;
use Ronda\Directory\Presentation\Livewire\ZoneList;

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

// Las zonas agrupan sedes; los cargos describen el organigrama. Los dos son
// estructura del cliente, y hasta ahora solo se podian sembrar por codigo.
Route::get('/zonas', ZoneList::class)->name('zones.index');
Route::get('/zonas/nueva', ZoneForm::class)->name('zones.create');
Route::get('/zonas/{zone}/editar', ZoneForm::class)->name('zones.edit');

Route::get('/cargos', PositionList::class)->name('positions.index');

// Vive en Directory y no en Identity porque lo que se administra son las sedes
// de esa persona, no la persona. La URL cuelga de /usuarios para que se llegue
// desde su ficha.
Route::get('/usuarios/{user}/sedes', UserSiteAssignments::class)->name('users.sites');
