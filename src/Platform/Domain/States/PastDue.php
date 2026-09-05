<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\States;

final class PastDue extends TenantStatus
{
    public static string $name = 'past_due';
}
