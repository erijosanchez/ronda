<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Ronda\Platform\Presentation\Http\Middleware\EnsureBackOfficeAccess;
use Ronda\Platform\Presentation\Http\Middleware\SecurityHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // El TLS lo termina el proxy que hay delante; sin esto Laravel arma
        // las URLs con http:// aunque APP_URL sea https.
        $middleware->trustProxies(at: '*');

        // Las cabeceras de seguridad se aplican a todo, no a un grupo.
        // Ver docs/adr/ y RONDA-PLAN-MAESTRO.md sec. 10.5
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            // Se llama `auth.back-office` y no `back-office` a proposito: es
            // autenticacion, y asi RouteProtectionTest —que busca middleware
            // de auth— lo reconoce como tal en vez de dar por desprotegidas
            // las rutas del equipo.
            'auth.back-office' => EnsureBackOfficeAccess::class,
            'role' => Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         | Un subdominio que no es de nadie es un 404, no un error del servidor.
         |
         | Sin esto, escribir mal la direccion —«acmee.ronda.pe»— devuelve un
         | 500: el cliente cree que el sistema se cayo, y en los registros queda
         | un error que no lo es. Ademas, con la pagina de depuracion activa el
         | renderizador tarda mas de lo que PHP permite y bloquea al worker
         | durante 30 segundos, asi que una direccion mal escrita basta para
         | dejar al servidor sin atender a nadie.
         |
         | Es un 404 y no una redireccion a la portada a proposito: decirle a
         | quien prueba subdominios «este no existe» es lo mismo que decirle
         | «este si», y eso no se le regala.
         */
        $exceptions->render(function (
            Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException $e,
        ): Illuminate\Http\Response {
            return response(view('errors.404')->render(), 404);
        });
    })->create();
