<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\ValueObjects;

/**
 * La foto de uso de un cliente: lo que tiene montado ahora mismo.
 * RONDA-PLAN-MAESTRO.md sec. 3.6
 *
 * Sedes y usuarios no son limites —el precio es por sede y los usuarios son
 * ilimitados a proposito, para que nadie racione accesos (sec. 15.1)—, pero se
 * muestran igual: son lo que explica la factura.
 */
final readonly class PlanUsage
{
    /**
     * @param  list<SiteStorage>  $storageBySite
     */
    public function __construct(
        public int $sites,
        public int $users,
        public int $templates,
        public array $storageBySite = [],
    ) {}

    public function totalBytes(): int
    {
        return array_sum(array_map(
            static fn (SiteStorage $sede): int => $sede->bytes,
            $this->storageBySite,
        ));
    }

    /**
     * La sede que mas ocupa, que es la unica que puede chocar contra el limite
     * por sede antes que ninguna otra.
     */
    public function busiestSite(): ?SiteStorage
    {
        $sedes = $this->storageBySite;

        usort($sedes, static fn (SiteStorage $a, SiteStorage $b): int => $b->bytes <=> $a->bytes);

        return $sedes[0] ?? null;
    }
}
