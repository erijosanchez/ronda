<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

// Denegar por defecto.
//
// En reports-trimax, `routes/api.php` exponia /api/admin/mapa-vivo,
// /historial-km, /resumen-diario y /recorrido sin ningun middleware, y los
// metodos del controlador tampoco comprobaban nada: cualquiera con la URL leia
// posiciones GPS, nombres y sedes de los motorizados. Este test existe para que
// eso no pueda repetirse sin que la CI lo detenga.
//
// Anadir una ruta a la lista blanca es una decision consciente que queda en el
// diff y pasa por revision. Olvidarse del middleware, no.

/**
 * Rutas publicas por diseno. Toda entrada necesita justificacion en el PR.
 *
 * @var list<string>
 */
$publicRoutes = [
    '/',                          // portada
    'up',                         // health check de Laravel
    'login',
    'logout',
    'register',
    'forgot-password',
    'reset-password',
    'reset-password/{token}',
    'two-factor-challenge',
    'sanctum/csrf-cookie',
    'storage/{path}',
];

/**
 * Prefijos de paquetes de terceros que gestionan su propia autorizacion.
 *
 * @var list<string>
 */
$vendorPrefixes = [
    'livewire/',
    'telescope',
    '_debugbar/',
    '_ignition/',
    'horizon',
    'sanctum/',
];

it('no expone ninguna ruta sin autenticacion', function () use ($publicRoutes, $vendorPrefixes) {
    $unprotected = [];

    /** @var RoutingRoute $route */
    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        if (in_array($uri, $publicRoutes, true)) {
            continue;
        }

        foreach ($vendorPrefixes as $prefix) {
            if (str_starts_with($uri, $prefix)) {
                continue 2;
            }
        }

        $middleware = $route->gatherMiddleware();

        $authenticated = collect($middleware)->contains(
            fn (mixed $m): bool => is_string($m) && (
                $m === 'auth'
                || str_starts_with($m, 'auth:')
                || str_starts_with($m, 'auth.')
                || str_contains($m, 'Authenticate')
            )
        );

        if (! $authenticated) {
            $unprotected[] = implode(' ', $route->methods()).' /'.$uri;
        }
    }

    expect($unprotected)->toBeEmpty(
        "Rutas sin middleware de autenticacion:\n  ".implode("\n  ", $unprotected).
        "\n\nSi la ruta debe ser publica, agregala a \$publicRoutes en este test y explica por que en el PR."
    );
})->group('security');

it('resuelve todos los controladores referenciados por las rutas', function () {
    // En reports-trimax, /api/ordenes/* apuntaba a cuatro metodos que no
    // existian en ComercialController. Fallaba con 500 al primer uso.
    $broken = [];

    /** @var RoutingRoute $route */
    foreach (Route::getRoutes() as $route) {
        $action = $route->getAction('uses');

        if (! is_string($action) || ! str_contains($action, '@')) {
            continue;
        }

        [$class, $method] = explode('@', $action, 2);

        if (! class_exists($class) || ! method_exists($class, $method)) {
            $broken[] = '/'.$route->uri().' -> '.$action;
        }
    }

    expect($broken)->toBeEmpty(
        "Rutas apuntando a controladores o metodos inexistentes:\n  ".implode("\n  ", $broken)
    );
})->group('security');
