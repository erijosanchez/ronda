<?php

declare(strict_types=1);

namespace Ronda\Submissions\Domain\States;

final class Approved extends SubmissionState
{
    public static string $name = 'approved';
}
