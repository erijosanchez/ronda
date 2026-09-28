<?php

declare(strict_types=1);

namespace Ronda\Identity\Application\Data;

use Ronda\Platform\Domain\Csv\ImportIssue;

/**
 * Lo que se va a hacer con un archivo de personas, antes de hacerlo.
 * RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Se calcula entero y se ensena; solo despues se escribe. Dar de alta a media
 * plantilla de una empresa y quedarse a la mitad es de las cosas que obligan a
 * limpiar a mano.
 */
final readonly class UserImportPlan
{
    /**
     * @param  list<PlannedUser>  $users
     * @param  list<ImportIssue>  $issues
     */
    public function __construct(
        public array $users = [],
        public array $issues = [],
    ) {}

    public function isValid(): bool
    {
        return $this->issues === [];
    }

    public function toCreate(): int
    {
        return count(array_filter($this->users, static fn (PlannedUser $persona): bool => ! $persona->isUpdate()));
    }

    public function toUpdate(): int
    {
        return count(array_filter($this->users, static fn (PlannedUser $persona): bool => $persona->isUpdate()));
    }
}
