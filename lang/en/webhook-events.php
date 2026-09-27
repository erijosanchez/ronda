<?php

declare(strict_types=1);

/**
 * Webhook event names. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * The key is the public event name and is never translated: it travels in the
 * body of the call and is part of the contract. This is only what is shown on
 * screen.
 */
return [
    'submission.submitted' => 'Someone submits a report',
    'submission.approved' => 'A report is approved',
    'submission.rejected' => 'A report is sent back',
    'obligation.missed' => 'A report is due and was not submitted',
];
