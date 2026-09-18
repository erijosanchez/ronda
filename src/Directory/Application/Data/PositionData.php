<?php

declare(strict_types=1);

namespace Ronda\Directory\Application\Data;

/**
 * Datos de un cargo, ya validados. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * El nivel ordena el organigrama; no concede permisos, que eso son los roles.
 */
final readonly class PositionData
{
    public function __construct(
        public string $name,
        public int $level = 0,
    ) {}

    /**
     * @return array<string, string|int>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'level' => $this->level,
        ];
    }
}
