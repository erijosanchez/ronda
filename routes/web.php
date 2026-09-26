<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas centrales
|--------------------------------------------------------------------------
| Estas son las rutas del dominio central del SaaS (marketing, registro,
| back-office de Ronda). Las rutas de tenant viven en routes/tenant.php y
| se registran con el middleware de identificacion de tenant.
|
| Denegar por defecto: toda ruta nueva nace dentro de un grupo autenticado.
| RouteProtectionTest recorre la tabla de rutas y falla si alguna se sale.
*/

/*
 | El sitio publico y el alta de clientes. Son publicos por definicion, y por
 | eso viven fuera del grupo de abajo: sus rutas estan justificadas una por una
 | en la lista blanca de RouteProtectionTest.
 */
require __DIR__.'/../src/Platform/Presentation/routes-central.php';

Route::middleware(['auth'])->group(function (): void {
    // Los modulos registran aqui sus rutas centrales conforme se construyan.
});
