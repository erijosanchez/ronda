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

    Route::middleware(['auth'])->group(function (): void {
        // Los modulos registran aqui sus rutas de tenant conforme se construyan.
        // Ejemplo (fase 1): Ronda\Submissions\Presentation\routes.php
    });

});
