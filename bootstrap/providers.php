<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\TenancyServiceProvider;
use Ronda\Directory\Presentation\Providers\DirectoryServiceProvider;
use Ronda\Evidence\Presentation\Providers\EvidenceServiceProvider;
use Ronda\Forms\Presentation\Providers\FormsServiceProvider;
use Ronda\Identity\Presentation\Providers\AuthorizationServiceProvider;
use Ronda\Identity\Presentation\Providers\FortifyServiceProvider;
use Ronda\Identity\Presentation\Providers\IdentityServiceProvider;
use Ronda\Insights\Presentation\Providers\InsightsServiceProvider;
use Ronda\Notifications\Infrastructure\Providers\NotificationsEventServiceProvider;
use Ronda\Notifications\Presentation\Providers\NotificationsServiceProvider;
use Ronda\Scheduling\Presentation\Providers\SchedulingServiceProvider;
use Ronda\Submissions\Presentation\Providers\SubmissionsServiceProvider;
use Ronda\Workflow\Presentation\Providers\WorkflowServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    AuthorizationServiceProvider::class,
    FortifyServiceProvider::class,
    IdentityServiceProvider::class,
    InsightsServiceProvider::class,
    DirectoryServiceProvider::class,
    FormsServiceProvider::class,
    SchedulingServiceProvider::class,
    SubmissionsServiceProvider::class,
    EvidenceServiceProvider::class,
    WorkflowServiceProvider::class,
    NotificationsServiceProvider::class,
    NotificationsEventServiceProvider::class,
];
