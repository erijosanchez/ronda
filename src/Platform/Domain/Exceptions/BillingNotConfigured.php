<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Exceptions;

use RuntimeException;

/**
 * Se eligio una pasarela que no esta lista para cobrar.
 *
 * Se lanza al ARRANCAR y no al primer cobro: enterarse de que faltan las llaves
 * cuando un cliente esta pagando es la peor forma posible de enterarse.
 */
final class BillingNotConfigured extends RuntimeException
{
    public static function missingKeys(string $gateway): self
    {
        return new self(
            "El cobro esta configurado con «{$gateway}» pero faltan sus llaves. ".
            'Ponlas en el .env o vuelve a BILLING_GATEWAY=manual.',
        );
    }

    public static function unknownGateway(string $gateway): self
    {
        return new self("No existe la pasarela «{$gateway}». Usa «manual» o «culqi».");
    }
}
