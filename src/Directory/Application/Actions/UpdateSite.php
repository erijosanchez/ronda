<?php

declare(strict_types=1);

namespace Ronda\Directory\Application\Actions;

use Ronda\Directory\Application\Data\SiteData;
use Ronda\Directory\Domain\Models\Site;

/**
 * Modifica una sede. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Recibe la sede ya resuelta: quien la busca es quien puede aplicar la Policy
 * y el scope de frontera. Si la Action la buscase por id, se saltaria ambos.
 */
final class UpdateSite
{
    public function __invoke(Site $site, SiteData $data): Site
    {
        $site->update($data->toAttributes());

        return $site->refresh();
    }
}
