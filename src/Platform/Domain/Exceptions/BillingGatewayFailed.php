<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Exceptions;

use RuntimeException;

/**
 * La pasarela no se pudo consultar, o respondio algo que no se entiende.
 *
 * NO es una tarjeta rechazada: eso es un resultado normal y viaja dentro de
 * ChargeResult. Esto es la pasarela caida, unas llaves mal puestas o un cambio
 * en su API — cosas que hay que mirar, no reintentar en bucle.
 */
final class BillingGatewayFailed extends RuntimeException
{
    public static function http(string $gateway, int $status, string $body): self
    {
        return new self("{$gateway} responded {$status}: ".mb_substr($body, 0, 500));
    }

    public static function unreachable(string $gateway, string $reason): self
    {
        return new self("{$gateway} is unreachable: {$reason}");
    }

    public static function unexpected(string $gateway, string $detail): self
    {
        return new self("{$gateway} returned something unexpected: {$detail}");
    }
}
