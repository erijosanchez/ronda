<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Billing;

/**
 * En que punto esta un cobro. RONDA-PLAN-MAESTRO.md sec. 15.1
 *
 * `pending` es lo emitido y no cobrado; `failed` es lo intentado y rechazado,
 * que no es lo mismo: lo primero espera, lo segundo se reintenta y cuenta para
 * cortar. `void` es lo anulado por nosotros, que nunca se cobra.
 */
enum InvoiceStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Void = 'void';

    public function isSettled(): bool
    {
        return $this === self::Paid || $this === self::Void;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Paid => __('Paid'),
            self::Failed => __('Failed'),
            self::Void => __('Void'),
        };
    }
}
