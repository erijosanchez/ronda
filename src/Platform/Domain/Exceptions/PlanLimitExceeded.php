<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Exceptions;

use DomainException;

/**
 * El plan contratado no da para mas. RONDA-PLAN-MAESTRO.md sec. 3.6
 *
 * El mensaje va en ingles porque es para los registros. Lo que lee el cliente
 * lo arma la pantalla con `__()` a partir de `limit` y `limitValue`, que es lo
 * que permite decirle «tu plan llega a 3 plantillas» y no «error de cuota».
 *
 * Llegar al limite no es un fallo del cliente ni un error del programa: es una
 * conversacion comercial. Por eso lleva el dato exacto y no solo un no.
 */
final class PlanLimitExceeded extends DomainException
{
    private function __construct(
        public readonly PlanLimit $limit,
        public readonly int $limitValue,
    ) {
        parent::__construct("Plan limit reached: {$limit->value} ({$limitValue}).");
    }

    public static function templates(int $limit): self
    {
        return new self(PlanLimit::Templates, $limit);
    }

    /**
     * @param  int  $gigabytesPerSite  Lo que el plan da por sede.
     */
    public static function storage(int $gigabytesPerSite): self
    {
        return new self(PlanLimit::StoragePerSite, $gigabytesPerSite);
    }
}
