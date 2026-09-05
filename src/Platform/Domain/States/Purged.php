<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\States;

final class Purged extends TenantStatus
{
    public static string $name = 'purged';
}
