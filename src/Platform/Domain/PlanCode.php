<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain;

/**
 * Los planes que existen. RONDA-PLAN-MAESTRO.md sec. 3.6
 *
 * Es un enum y no solo filas en la tabla porque el codigo necesita nombrar el
 * plan de entrada: quien se registra hoy entra en Starter, y eso es una
 * decision de negocio, no un dato editable.
 *
 * Lo que cada plan incluye SI vive en la tabla: los precios y los limites
 * cambian con el mercado, y cambiarlos no puede exigir un despliegue.
 */
enum PlanCode: string
{
    case Starter = 'starter';
    case Pro = 'pro';
    case Enterprise = 'enterprise';

    /**
     * Con el que empieza quien se registra solo.
     *
     * Enterprise es cotizado y Pro se elige pagando; el alta self-service no
     * puede dar ninguno de los dos sin que nadie lo mire.
     */
    public static function default(): self
    {
        return self::Starter;
    }
}
