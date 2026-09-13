<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Platform\Application\Actions\CreateTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Models\Tenant;

// Recorrido de todas las rutas autenticadas con un usuario del otro tenant.
// RONDA-PLAN-MAESTRO.md sec. 7.5
//
// La prueba se escribe generica a proposito: recorre la tabla de rutas, no una
// lista escrita a mano. Cada ruta autenticada que se anade queda cubierta sin
// tocar este archivo, que es lo que hace que el invariante no se erosione.

const CLAVE_CRUZADA = 'una-contrasena-larga-de-prueba';

function tenantCruzado(string $slug): Tenant
{
    return resolve(CreateTenant::class)(new CreateTenantData(
        name: ucfirst($slug),
        slug: $slug,
        domain: $slug.'.ronda.test',
        ownerName: 'Duena de '.$slug,
        ownerEmail: 'owner@'.$slug.'.test',
        ownerPassword: CLAVE_CRUZADA,
    ));
}

/**
 * Rutas GET que exigen sesion y no llevan parametros obligatorios.
 *
 * @return list<string>
 */
function rutasAutenticadas(): array
{
    $uris = [];

    /** @var RoutingRoute $route */
    foreach (Route::getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }

        // gatherMiddleware() NO expande los grupos, asi que aqui llega el alias
        // 'auth' tal cual. Filtrar solo por 'Authenticate' casaba unicamente con
        // la clase de Horizon y dejaba fuera /panel y /sedes: el recorrido
        // parecia verde sin haber probado ninguna ruta propia.
        $exigeSesion = collect($route->gatherMiddleware())->contains(
            fn (mixed $m): bool => is_string($m) && (
                $m === 'auth'
                || str_starts_with($m, 'auth:')
                || str_contains($m, 'Authenticate')
            ),
        );

        if (! $exigeSesion) {
            continue;
        }

        // Las rutas con parametros necesitan un recurso concreto; se cubren en
        // las pruebas del modulo que las declara.
        if (str_contains($route->uri(), '{')) {
            continue;
        }

        $uris[] = $route->uri();
    }

    return array_values(array_unique($uris));
}

it('recorre las rutas propias de la aplicacion, no solo las de terceros', function (): void {
    // Ancla contra un recorrido vacio de contenido. La primera version filtraba
    // mal y solo barria las rutas de Horizon: pasaba en verde sin haber pedido
    // ni una pantalla de la aplicacion.
    $rutas = rutasAutenticadas();

    expect($rutas)->toContain('panel')
        ->and($rutas)->toContain('sedes');
})->group('tenancy');

it('no sirve datos del tenant B a un usuario autenticado en el tenant A', function (): void {
    $a = tenantCruzado('alfa');
    $b = tenantCruzado('beta');

    // Un dato inconfundible de B: si aparece en alguna respuesta, se filtro.
    $b->run(function (): void {
        Site::factory()->create(['code' => 'SECRETO-DE-BETA', 'name' => 'Sede secreta de beta']);
    });

    // Sesion abierta de verdad en alfa, no con actingAs: se trata de ejercitar
    // el camino real, cookie incluida.
    $this->post('http://alfa.ronda.test/login', [
        'email' => 'owner@alfa.test',
        'password' => CLAVE_CRUZADA,
    ]);
    $this->assertAuthenticated();

    $filtradas = [];

    foreach (rutasAutenticadas() as $uri) {
        $respuesta = $this->get('http://beta.ronda.test/'.$uri);

        if ($respuesta->status() === 200) {
            $filtradas[] = 'GET /'.$uri.' devolvio 200';
        }

        if (str_contains($respuesta->getContent() ?: '', 'SECRETO-DE-BETA')) {
            $filtradas[] = 'GET /'.$uri.' expuso datos de beta';
        }
    }

    expect($filtradas)->toBeEmpty(
        "Un usuario de alfa alcanzo el tenant beta:\n  ".implode("\n  ", $filtradas),
    );

    dropTenantDatabase($a);
    dropTenantDatabase($b);
})->group('tenancy');
