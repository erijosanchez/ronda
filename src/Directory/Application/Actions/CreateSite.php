<?php

declare(strict_types=1);

namespace Ronda\Directory\Application\Actions;

use Ronda\Directory\Application\Data\SiteData;
use Ronda\Directory\Domain\Models\Site;

/**
 * Da de alta una sede. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Escribe en una sola tabla, asi que no abre transaccion: la regla 3 la exige
 * para escrituras multi-tabla. En cuanto el alta arrastre asignaciones o
 * catalogos, esto tiene que envolverse.
 */
final class CreateSite
{
    public function __invoke(SiteData $data): Site
    {
        return Site::create($data->toAttributes());
    }
}
