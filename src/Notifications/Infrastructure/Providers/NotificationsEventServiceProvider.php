<?php

declare(strict_types=1);

namespace Ronda\Notifications\Infrastructure\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Ronda\Notifications\Application\Contracts\OperationalMessenger;
use Ronda\Notifications\Infrastructure\Listeners\NotifyAuthorOfDecision;
use Ronda\Notifications\Infrastructure\Listeners\NotifyReviewersOfSubmission;
use Ronda\Notifications\Infrastructure\Notifications\LaravelOperationalMessenger;
use Ronda\Submissions\Domain\Events\SubmissionSubmitted;
use Ronda\Workflow\Domain\Events\SubmissionApproved;
use Ronda\Workflow\Domain\Events\SubmissionRejected;

/**
 * Enchufa la infraestructura de avisos: quien escucha que, y por donde salen.
 *
 * Vive en `Infrastructure` y no junto a las vistas porque es donde estan las
 * piezas que registra: `Presentation` no puede depender de `Infrastructure`
 * (deptrac lo verifica), y este proveedor es su raiz de composicion.
 *
 * Los oyentes se enlazan aqui y no en los modulos que lanzan los eventos: quien
 * entrega o aprueba no tiene por que saber que alguien avisa. Si este modulo se
 * quitara, el resto seguiria funcionando en silencio.
 *
 * El despachador de eventos se inyecta en vez de usar la facade `Event`: fuera
 * de `Presentation` no se usan facades (CLAUDE.md, regla 8).
 */
final class NotificationsEventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OperationalMessenger::class, LaravelOperationalMessenger::class);
    }

    public function boot(Dispatcher $events): void
    {
        $events->listen(SubmissionSubmitted::class, NotifyReviewersOfSubmission::class);
        $events->listen(SubmissionApproved::class, NotifyAuthorOfDecision::class);
        $events->listen(SubmissionRejected::class, NotifyAuthorOfDecision::class);
    }
}
