<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronda\Platform\Presentation\Livewire\StartWizard;

/*
| Rutas de Platform dentro del cliente.
|
| Se incluyen desde routes/tenant.php, ya dentro del grupo que identifica el
| tenant por dominio y exige sesion. Las del dominio central —el registro— van
| en routes-central.php, que es el unico archivo de rutas del proyecto que vive
| fuera de la zona autenticada.
*/

Route::get('/bienvenida', StartWizard::class)->name('onboarding');
