<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Ronda\Api\Domain\ApiScope;

// Deny by default en la API. RONDA-PLAN-MAESTRO.md sec. 13.1
//
// «Cada endpoint declara su alcance y su Policy; un test recorre la tabla de
// rutas y falla si alguno no lo hace. Es el control que faltaba en el sistema
// actual.»
//
// Sin esto, una ruta nueva nace abierta por olvido y nadie se entera hasta que
// alguien la encuentra. La comprobacion es barata y no depende de que quien
// escriba el siguiente endpoint se acuerde.

/**
 * @return list<RoutingRoute>
 */
function rutasDeLaApi(): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        static fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'api/v1/'),
    ));
}

it('exige token en todos los endpoints de la API', function (): void {
    $sinToken = [];

    foreach (rutasDeLaApi() as $ruta) {
        if (! in_array('auth:sanctum', $ruta->gatherMiddleware(), true)) {
            $sinToken[] = implode('|', $ruta->methods()).' /'.$ruta->uri();
        }
    }

    expect($sinToken)->toBeEmpty(
        "Endpoints de la API sin `auth:sanctum`:\n  ".implode("\n  ", $sinToken),
    );
})->group('security');

it('exige que cada endpoint declare su alcance', function (): void {
    $sinAlcance = [];
    $validos = array_map(static fn (ApiScope $s): string => $s->value, ApiScope::cases());

    foreach (rutasDeLaApi() as $ruta) {
        $alcances = array_values(array_filter(
            $ruta->gatherMiddleware(),
            static fn (mixed $m): bool => is_string($m) && str_starts_with($m, 'scope:'),
        ));

        if ($alcances === []) {
            $sinAlcance[] = implode('|', $ruta->methods()).' /'.$ruta->uri().' (ninguno)';

            continue;
        }

        // Y que sea uno de los que existen: `scope:sedes:leer` pasaria el
        // filtro de arriba y no lo tendria ningun token, asi que el endpoint
        // quedaria muerto sin que nadie lo notara.
        foreach ($alcances as $declarado) {
            $valor = mb_substr($declarado, mb_strlen('scope:'));

            if (! in_array($valor, $validos, true)) {
                $sinAlcance[] = implode('|', $ruta->methods()).' /'.$ruta->uri()." (inventado: {$valor})";
            }
        }
    }

    expect($sinAlcance)->toBeEmpty(
        "Endpoints de la API sin alcance declarado, o con uno que no existe:\n  ".
        implode("\n  ", $sinAlcance).
        "\n\nDeclaralo en la ruta: ->middleware('scope:'.ApiScope::LoQueSea->value)",
    );
})->group('security');

it('comprueba el plan y la cuota en todos los endpoints', function (): void {
    $sinControl = [];

    foreach (rutasDeLaApi() as $ruta) {
        $middleware = $ruta->gatherMiddleware();

        $tienePlan = collect($middleware)->contains(fn (mixed $m): bool => is_string($m) && str_contains($m, 'EnsureApiIsInPlan'));
        $tieneCuota = collect($middleware)->contains(fn (mixed $m): bool => is_string($m) && str_contains($m, 'ApiRateLimit'));

        if (! $tienePlan || ! $tieneCuota) {
            $sinControl[] = implode('|', $ruta->methods()).' /'.$ruta->uri();
        }
    }

    expect($sinControl)->toBeEmpty(
        "Endpoints sin comprobacion de plan o sin cuota:\n  ".implode("\n  ", $sinControl),
    );
})->group('security');

it('no expone la API en el dominio central', function (): void {
    // Las rutas de la API son de tenant: en el central no hay cliente al que
    // pertenezcan los datos.
    $this->getJson('http://localhost/api/v1/sites')->assertNotFound();
})->group('security');
