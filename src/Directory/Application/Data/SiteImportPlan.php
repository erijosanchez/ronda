<?php

declare(strict_types=1);

namespace Ronda\Directory\Application\Data;

use Ronda\Platform\Domain\Csv\ImportIssue;

/**
 * Lo que se va a hacer con un archivo de sedes, antes de hacerlo.
 * RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * El plan se calcula entero y se ensena; solo despues se escribe. Un
 * importador que va escribiendo mientras lee deja al cliente con media
 * estructura cargada y un mensaje de error, y nadie sabe por donde iba.
 */
final readonly class SiteImportPlan
{
    /**
     * @param  list<PlannedSite>  $sites
     * @param  list<ImportIssue>  $issues
     */
    public function __construct(
        public array $sites = [],
        public array $issues = [],
    ) {}

    public function isValid(): bool
    {
        return $this->issues === [];
    }

    public function toCreate(): int
    {
        return count(array_filter($this->sites, static fn (PlannedSite $sede): bool => ! $sede->isUpdate()));
    }

    public function toUpdate(): int
    {
        return count(array_filter($this->sites, static fn (PlannedSite $sede): bool => $sede->isUpdate()));
    }
}
