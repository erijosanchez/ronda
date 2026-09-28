<?php

declare(strict_types=1);

namespace Ronda\Identity\Application\Data;

/**
 * Una persona lista para escribirse. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Las sedes viajan aparte del UserData porque no son de la persona: son de su
 * asignacion, que es otra tabla y otra Action (AssignUserToSites).
 *
 * `siteIds` a null significa «el archivo no traia la columna»: no es lo mismo
 * que traerla vacia, que si querria decir «quitale todas las sedes».
 */
final readonly class PlannedUser
{
    /**
     * @param  list<int>|null  $siteIds
     */
    public function __construct(
        public UserData $data,
        public ?int $existingId = null,
        public ?array $siteIds = null,
    ) {}

    public function isUpdate(): bool
    {
        return $this->existingId !== null;
    }
}
