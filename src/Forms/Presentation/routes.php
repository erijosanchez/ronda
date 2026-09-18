<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronda\Forms\Presentation\Livewire\TemplateCatalog;
use Ronda\Forms\Presentation\Livewire\TemplateDesigner;
use Ronda\Forms\Presentation\Livewire\TemplateList;

/*
| Rutas del modulo Forms.
|
| Se incluyen desde routes/tenant.php, ya dentro del grupo que identifica el
| tenant por dominio y exige sesion.
|
| Rutas en espanol y en kebab-case (CLAUDE.md).
*/

Route::get('/plantillas', TemplateList::class)->name('templates.index');
Route::get('/plantillas/nueva', TemplateDesigner::class)->name('templates.create');

// El catalogo de arranque (sec. 3.5). Antes que la ruta con parametro para que
// «catalogo» no se lea como el codigo de una plantilla.
Route::get('/plantillas/catalogo', TemplateCatalog::class)->name('templates.catalog');
Route::get('/plantillas/{template}/disenar', TemplateDesigner::class)->name('templates.design');
