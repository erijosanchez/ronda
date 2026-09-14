<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
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
Route::get('/plantillas/{template}/disenar', TemplateDesigner::class)->name('templates.design');
