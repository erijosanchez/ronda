<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronda\Platform\Presentation\Http\LeaveImpersonationController;
use Ronda\Platform\Presentation\Livewire\PlanAndUsage;
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

// Plan contratado y consumo (sec. 3.6). Solo lectura: cambiar de plan llega
// con la facturacion.
Route::get('/plan', PlanAndUsage::class)->name('plan');

// Salir de una sesion de soporte (sec. 15.4). Va dentro de la zona autenticada
// porque solo tiene sentido con sesion abierta: es el boton del banner.
Route::post('/suplantacion/salir', LeaveImpersonationController::class)->name('impersonation.leave');
