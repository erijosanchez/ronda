<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
            'role' => Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
