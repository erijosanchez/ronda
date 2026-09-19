<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Billing;

/**
 * En que punto esta la suscripcion de un cliente.
 * RONDA-PLAN-MAESTRO.md sec. 15.1
 *
 *   trialing -> active -> past_due -> canceled
 *
 * Es un enum y no una maquina de estados como la del tenant (ADR 0007) porque
 * aqui no hay reglas de transicion que valga la pena declarar: el estado lo
 * dicta el cobro, y quien manda sobre el acceso es el estado del TENANT, que
 * si es una maquina de estados.
 */
enum SubscriptionStatus: string
{
    /** Periodo de prueba: no se ha cobrado nada todavia. */
    case Trialing = 'trialing';

    /** Al dia. */
    case Active = 'active';

    /** Un cobro fallo y se estan haciendo los reintentos. */
    case PastDue = 'past_due';

    /** Cancelada: no se vuelve a cobrar. */
    case Canceled = 'canceled';

    public function isBillable(): bool
    {
        return in_array($this, [self::Active, self::PastDue, self::Trialing], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Trialing => __('Trial'),
            self::Active => __('Up to date'),
            self::PastDue => __('Payment failed'),
            self::Canceled => __('Canceled'),
        };
    }
}
