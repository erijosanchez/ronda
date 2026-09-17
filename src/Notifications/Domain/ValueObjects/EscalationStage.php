<?php

declare(strict_types=1);

namespace Ronda\Notifications\Domain\ValueObjects;

use Ronda\Notifications\Domain\Audience;

/**
 * Un peldano de la escalera de escalamiento.
 */
final readonly class EscalationStage
{
    public function __construct(
        public int $level,
        public int $afterMinutes,
        public Audience $audience,
    ) {}
}
