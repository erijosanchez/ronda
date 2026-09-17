<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronda\Notifications\Presentation\Livewire\NotificationList;

/*
| Rutas del modulo Notifications.
|
| Se incluyen desde routes/tenant.php, ya dentro del grupo que identifica el
| tenant por dominio y exige sesion.
|
| Rutas en espanol y en kebab-case (CLAUDE.md).
*/

Route::get('/notificaciones', NotificationList::class)->name('notifications.index');
