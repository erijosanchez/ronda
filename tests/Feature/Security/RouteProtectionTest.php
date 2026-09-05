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
 * Rutas de paquetes de terceros: assets estaticos y endpoints que gestionan su
 * propia autorizacion. Livewire usa un prefijo con hash, de ahi los patrones.
 *
 * Ninguna de estas sirve datos del tenant: son JavaScript, CSS, banderas de
 * pais y el endpoint de actualizacion de componentes, que autoriza cada
 * componente por separado.
 *
 * @var list<string>
 */
$vendorPatterns = [
    '#^livewire[-/]#',        // livewire-<hash>/update, /livewire.js, uploads
    '#^flux/#',               // assets de Flux UI
    '#^passkeys/login#',      // reto WebAuthn: publico por definicion
    '#^tenancy/assets/#',     // assets servidos por stancl/tenancy
    '#^horizon#',             // autoriza con su propio gate
    '#^_debugbar/#',
    '#^_ignition/#',
];

it('no expone ninguna ruta sin autenticacion', function () use ($publicRoutes, $vendorPatterns): void {
    $unprotected = [];

    /** @var RoutingRoute $route */
    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        if (in_array($uri, $publicRoutes, true)) {
            continue;
        }

        foreach ($vendorPatterns as $pattern) {
            if (preg_match($pattern, $uri) === 1) {
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
            ),
        );

        if (! $authenticated) {
            $unprotected[] = implode(' ', $route->methods()).' /'.$uri;
        }
    }

    expect($unprotected)->toBeEmpty(
        "Rutas sin middleware de autenticacion:\n  ".implode("\n  ", $unprotected).
        "\n\nSi la ruta debe ser publica, agregala a \$publicRoutes en este test y explica por que en el PR.",
    );
})->group('security');

it('resuelve todos los controladores referenciados por las rutas', function (): void {
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
        "Rutas apuntando a controladores o metodos inexistentes:\n  ".implode("\n  ", $broken),
    );
})->group('security');
