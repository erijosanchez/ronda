<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\States;

final class Suspended extends TenantStatus
{
    public static string $name = 'suspended';
}
