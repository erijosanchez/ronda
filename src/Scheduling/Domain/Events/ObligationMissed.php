<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Domain\Events;

/**
 * Una obligacion cerro sin entrega. ADR 0008 y sec. 13.2
 *
 * Lleva solo el identificador, como el resto de eventos del proyecto: quien lo
 * escuche lo leera de la base cuando le toque, que puede ser minutos despues y
 * en otro proceso. Un evento que arrastra el modelo entero se queda con una
 * foto vieja.
 */
final readonly class ObligationMissed
{
    public function __construct(
        public int $obligationId,
    ) {}
}
