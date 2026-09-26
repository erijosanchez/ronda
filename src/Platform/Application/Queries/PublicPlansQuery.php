<?php

declare(strict_types=1);

namespace Ronda\Platform\Application\Queries;

use Illuminate\Support\Collection;
use Ronda\Platform\Domain\Models\Plan;

/**
 * Los planes que se muestran en el sitio publico.
 * RONDA-PLAN-MAESTRO.md sec. 3.6
 *
 * Salen de la tabla `plans`, no del HTML: el precio de la pagina y el precio
 * que se cobra tienen que ser el mismo numero, y si estuviera escrito en una
 * vista acabarian separandose el dia que alguien cambie la tarifa. Cambiarla
 * sigue siendo un UPDATE.
 *
 * Los planes cotizados (`is_public` en false) NO salen: no tienen precio de
 * lista, y ensenar uno seria inventarselo.
 *
 * @return Collection<int, Plan>
 */
final readonly class PublicPlansQuery
{
    /**
     * @return Collection<int, Plan>
     */
    public function __invoke(): Collection
    {
        return Plan::query()
            ->where('is_public', true)
            ->orderBy('position')
            ->get();
    }
}
