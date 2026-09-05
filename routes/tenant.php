<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Rutas de tenant
|--------------------------------------------------------------------------
| Todo lo que se sirve en el subdominio de un cliente. El middleware
| identifica el tenant por dominio y cambia la conexion de base de datos
| antes de que corra nada.
|
| PreventAccessFromCentralDomains devuelve 404 si alguien pide una ruta de
| tenant desde el dominio central: por eso este archivo NO puede declarar una
| ruta '/', o eclipsaria la portada del SaaS. El stub del paquete traia una y
| era exactamente eso lo que rompia la ruta central.
|
| Denegar por defecto: los modulos registran aqui dentro del grupo 'auth'.
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function (): void {

    /*
     | Autenticacion.
     |
     | Las rutas de Fortify se cargan AQUI y no donde el paquete las pone por
     | defecto. Fortify las registra en el grupo `web` del dominio central, sin
     | tenancy, y ahi el login consultaria la tabla `users` de la base central,
     | que no existe: los usuarios viven en la base del tenant (sec. 8.3).
     |
     | El registro por defecto se desactiva con Fortify::ignoreRoutes() en
     | Ronda\Identity\Presentation\Providers\FortifyServiceProvider.
     |
     | Van dentro del grupo de tenancy pero FUERA del grupo 'auth': iniciar
     | sesion es, por definicion, lo que hace quien todavia no la tiene.
     | RouteProtectionTest las tiene en su lista blanca.
     */
    Route::namespace('Laravel\Fortify\Http\Controllers')
        ->group(base_path('vendor/laravel/fortify/routes/routes.php'));

    Route::middleware(['auth'])->group(function (): void {
        // Los modulos registran aqui sus rutas de tenant conforme se construyan.
        // Ejemplo (fase 1): Ronda\Submissions\Presentation\routes.php
    });

});
