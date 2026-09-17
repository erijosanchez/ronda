<?php

declare(strict_types=1);

namespace Ronda\Submissions\Domain\States;

final class UnderReview extends SubmissionState
{
    public static string $name = 'under_review';
}
