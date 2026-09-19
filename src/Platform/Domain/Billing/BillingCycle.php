<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Billing;

/**
 * Cada cuanto se cobra. RONDA-PLAN-MAESTRO.md sec. 3.6
 *
 * El anual cobra diez meses en vez de doce. No es solo un descuento comercial:
 * cobrar una vez al ano en lugar de doce quita once ocasiones de que una
 * tarjeta sea rechazada, y once comisiones de pasarela.
 */
enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    /**
     * Meses que se cobran en un periodo.
     */
    public function billedMonths(): int
    {
        return match ($this) {
            self::Monthly => 1,
            // Doce meses de servicio menos los que regala el plan.
            self::Yearly => 12 - (int) config('billing.yearly_free_months', 2),
        };
    }

    /**
     * Meses que dura el periodo, que no son los mismos que se cobran.
     */
    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Yearly => 12,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Monthly => __('Monthly'),
            self::Yearly => __('Yearly'),
        };
    }
}
