<?php

declare(strict_types=1);

namespace Ronda\Api\Infrastructure\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Ronda\Api\Domain\Webhooks\WebhookSender;
use Ronda\Api\Infrastructure\Listeners\PublishWebhooks;
use Ronda\Api\Infrastructure\Webhooks\HttpWebhookSender;
use Ronda\Scheduling\Domain\Events\ObligationMissed;
use Ronda\Submissions\Domain\Events\SubmissionSubmitted;
use Ronda\Workflow\Domain\Events\SubmissionApproved;
use Ronda\Workflow\Domain\Events\SubmissionRejected;

/**
 * Enlaza los eventos del dominio con los avisos salientes.
 * RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Vive en `Infrastructure` por la misma razon que el de Notifications: lo que
 * registra son oyentes de infraestructura, y `Presentation` no puede depender
 * de `Infrastructure` (deptrac lo verifica). El despachador se inyecta en vez
 * de usar la facade `Event` (CLAUDE.md, regla 8).
 */
final class WebhookEventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Quien manda los avisos por HTTP. El Job pide el contrato, que vive
        // en Domain: Application no puede depender de Infrastructure.
        $this->app->bind(WebhookSender::class, HttpWebhookSender::class);
    }

    public function boot(Dispatcher $events): void
    {
        $events->listen(SubmissionSubmitted::class, [PublishWebhooks::class, 'submitted']);
        $events->listen(SubmissionApproved::class, [PublishWebhooks::class, 'approved']);
        $events->listen(SubmissionRejected::class, [PublishWebhooks::class, 'rejected']);
        $events->listen(ObligationMissed::class, [PublishWebhooks::class, 'missed']);
    }
}
