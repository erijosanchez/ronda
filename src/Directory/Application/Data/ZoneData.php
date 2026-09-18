<?php

declare(strict_types=1);

namespace Ronda\Directory\Application\Data;

/**
 * Datos de una zona, ya validados. RONDA-PLAN-MAESTRO.md sec. 8.3
 */
final readonly class ZoneData
{
    public function __construct(
        public string $name,
        public ?int $parentId = null,
        public ?int $managerId = null,
    ) {}

    /**
     * @return array<string, string|int|null>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'parent_id' => $this->parentId,
            'manager_id' => $this->managerId,
        ];
    }
}
