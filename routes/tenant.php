<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronda\Platform\Presentation\Http\Middleware\EnsureSessionBelongsToTenant;
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
    // Despues de identificar el tenant: comprueba que la sesion presentada se
    // abrio en ESTE cliente y no en otro. Ver el middleware.
    EnsureSessionBelongsToTenant::class,
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

    /*
     | Zona autenticada. Cada modulo aporta su propio archivo de rutas; ninguno
     | declara middleware por su cuenta, para que quien entra se decida en un
     | solo sitio.
     */
    Route::middleware(['auth'])->group(function (): void {
        require __DIR__.'/../src/Insights/Presentation/routes.php';
        require __DIR__.'/../src/Directory/Presentation/routes.php';
        require __DIR__.'/../src/Identity/Presentation/routes.php';
        require __DIR__.'/../src/Forms/Presentation/routes.php';
        require __DIR__.'/../src/Scheduling/Presentation/routes.php';
        require __DIR__.'/../src/Submissions/Presentation/routes.php';
        require __DIR__.'/../src/Evidence/Presentation/routes.php';
    });

});
