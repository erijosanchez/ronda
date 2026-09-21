<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronda\Platform\Presentation\Http\BackOfficeLogoutController;
use Ronda\Platform\Presentation\Livewire\BackOfficeLogin;
use Ronda\Platform\Presentation\Livewire\BackOfficeTenant;
use Ronda\Platform\Presentation\Livewire\BackOfficeTenants;
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

/*
| Back-office de Ronda (sec. 15.4).
|
| Vive en el dominio central y con su propio guard: quien da soporte no es
| usuario de ningun cliente. La unica ruta abierta es el acceso, que esta en la
| lista blanca de RouteProtectionTest; todo lo demas exige sesion del equipo
| CON segundo factor, que es lo que comprueba `auth.back-office`.
|
| El prefijo va en espanol como el resto (CLAUDE.md).
*/
Route::prefix('soporte')->group(function (): void {
    Route::get('/acceso', BackOfficeLogin::class)
        ->middleware('throttle:20,1')
        ->name('back-office.login');

    Route::middleware('auth.back-office')->group(function (): void {
        Route::post('/salir', BackOfficeLogoutController::class)->name('back-office.logout');

        Route::get('/clientes', BackOfficeTenants::class)->name('back-office.tenants');
        Route::get('/clientes/{tenant}', BackOfficeTenant::class)->name('back-office.tenant');
    });
});
