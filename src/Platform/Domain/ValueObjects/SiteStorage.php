<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\ValueObjects;

/**
 * Lo que ocupa la evidencia de una sede.
 *
 * Por sede y no por cliente porque asi se vende y asi se limita (sec. 3.6): el
 * plan da tantos GB POR SEDE, y una sede que se pasa no deja sin espacio a las
 * demas.
 */
final readonly class SiteStorage
{
    public function __construct(
        public int $siteId,
        public string $name,
        public int $bytes,
    ) {}

    public function gigabytes(): float
    {
        return round($this->bytes / 1024 ** 3, 2);
    }

    /**
     * Que porcentaje del limite lleva usado, o null si el plan no pone
     * ninguno: una barra al 0 % para siempre no dice nada.
     */
    public function percentageOf(?int $limitBytes): ?int
    {
        if ($limitBytes === null || $limitBytes === 0) {
            return null;
        }

        return (int) min(100, round($this->bytes / $limitBytes * 100));
    }
}
