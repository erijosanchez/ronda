<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronda\Identity\Presentation\Livewire\UserForm;
use Ronda\Identity\Presentation\Livewire\UserList;

/*
| Rutas del modulo Identity.
|
| Se incluyen desde routes/tenant.php, ya dentro del grupo que identifica el
| tenant por dominio y exige sesion.
|
| Rutas en espanol y en kebab-case (CLAUDE.md).
*/

Route::get('/usuarios', UserList::class)->name('users.index');
Route::get('/usuarios/nuevo', UserForm::class)->name('users.create');
Route::get('/usuarios/{user}/editar', UserForm::class)->name('users.edit');
