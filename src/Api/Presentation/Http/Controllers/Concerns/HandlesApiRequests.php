<?php

declare(strict_types=1);

namespace Ronda\Api\Presentation\Http\Controllers\Concerns;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Lo comun a los endpoints de la v1. RONDA-PLAN-MAESTRO.md sec. 13.1
 *
 * Es un trait y no una clase base abstracta porque en este proyecto las clases
 * son finales (regla 7, con test de arquitectura). La unica excepcion son los
 * estados declarativos, y un controlador no es eso.
 *
 * Trae dos cosas y nada mas: `authorize()`, que no viene en el controlador base
 * de Laravel 12, y el tamano de pagina.
 */
trait HandlesApiRequests
{
    use AuthorizesRequests;

    /**
     * Cuantos elementos por pagina, con tope.
     *
     * El tope no es capricho: sin el, `?per_page=100000` convierte cualquier
     * coleccion en una descarga de la base entera, que es a la vez un problema
     * de memoria y la forma mas comoda de llevarse los datos de un cliente.
     */
    protected function perPage(): int
    {
        $pedido = (int) request()->integer('per_page', (int) config('api.per_page', 50));

        return max(1, min($pedido, (int) config('api.max_per_page', 200)));
    }
}
