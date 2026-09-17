<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronda\Scheduling\Presentation\Livewire\ScheduleForm;
use Ronda\Scheduling\Presentation\Livewire\ScheduleList;

/*
| Rutas del modulo Scheduling.
|
| Se incluyen desde routes/tenant.php, ya dentro del grupo que identifica el
| tenant por dominio y exige sesion.
|
| Rutas en espanol y en kebab-case (CLAUDE.md).
*/

Route::get('/programaciones', ScheduleList::class)->name('schedules.index');
Route::get('/programaciones/nueva', ScheduleForm::class)->name('schedules.create');
Route::get('/programaciones/{schedule}/editar', ScheduleForm::class)->name('schedules.edit');
