<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Domain\ValueObjects;

/**
 * Lo unico que el planificador necesita saber de una sede.
 *
 * Existe para que ObligationPlanner no dependa del modelo Site: el dominio de
 * Scheduling se prueba sin base de datos y sin conocer Eloquent.
 */
final readonly class SiteCalendar
{
    public function __construct(
        public int $siteId,
        public string $timezone,
        public ?string $activeFrom = null,
        public ?string $activeUntil = null,
    ) {}

    /**
     * Una sede cerrada o que todavia no abrio no genera obligaciones: sin
     * esto, el KPI de cumplimiento se llenaria de incumplimientos de locales
     * que no existian.
     */
    public function isOpenOn(string $date): bool
    {
        if ($this->activeFrom !== null && $date < $this->activeFrom) {
            return false;
        }

        return $this->activeUntil === null || $date <= $this->activeUntil;
    }
}
