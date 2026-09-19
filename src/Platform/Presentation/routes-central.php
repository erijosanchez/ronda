<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronda\Platform\Presentation\Livewire\RegisterForm;
use Ronda\Platform\Presentation\Livewire\TenantProvisioning;

/*
| Rutas centrales del modulo Platform.
|
| Se incluyen desde routes/web.php, FUERA del grupo autenticado: quien se
| registra no tiene cuenta todavia, y quien espera a que se cree la suya
| tampoco. Las dos estan en la lista blanca de RouteProtectionTest.
|
| El `throttle` frena la peticion de la pagina; el limite que de verdad importa
| —cuantas cuentas se crean por hora desde una IP— esta en el componente,
| porque el envio de un formulario Livewire no pasa por esta ruta.
*/

Route::middleware('throttle:30,1')->group(function (): void {
    Route::get('/registro', RegisterForm::class)->name('register.tenant');

    // El ULID del cliente es lo que hace privada esta direccion. No lleva
    // sesion porque todavia no hay ninguna que llevar.
    Route::get('/registro/{tenant}/preparando', TenantProvisioning::class)->name('register.waiting');
});
