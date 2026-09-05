<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Events;

use Ronda\Platform\Domain\Models\Tenant;

/**
 * El tenant ya tiene base, esquema, catalogos y propietario: puede entrar.
 * RONDA-PLAN-MAESTRO.md sec. 7.2
 */
final readonly class TenantProvisioned
{
    public function __construct(
        public Tenant $tenant,
    ) {}
}
