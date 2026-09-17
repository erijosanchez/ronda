<?php

declare(strict_types=1);

namespace Ronda\Notifications\Application\Actions;

use Carbon\CarbonImmutable;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Notifications\Application\Data\NotificationMessage;
use Ronda\Notifications\Application\Queries\NotificationRecipientsQuery;
use Ronda\Notifications\Application\Queries\TenantUrlQuery;
use Ronda\Notifications\Domain\Audience;
use Ronda\Notifications\Domain\NotificationTopic;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Scheduling\Domain\States\Pending;

/**
 * «Te quedan dos horas». RONDA-PLAN-MAESTRO.md sec. 9.3
 *
 * El recordatorio anticipado solo es posible porque la obligacion existe antes
 * de vencer (ADR 0008). Se avisa una sola vez por obligacion (NotifyOnce) y
 * solo si la ventana YA ABRIO: recordar algo que todavia no se puede entregar
 * es ruido, y el ruido se acaba ignorando.
 */
final readonly class SendObligationReminders
{
    public function __construct(
        private NotificationRecipientsQuery $recipients,
        private NotifyOnce $notifyOnce,
        private TenantUrlQuery $url,
    ) {}

    /**
     * @return int cuantos recordatorios se mandaron
     */
    public function __invoke(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now('UTC');
        $hasta = $now->addMinutes((int) config('notifications.due_soon_minutes', 120));

        $mandados = 0;

        $obligaciones = Obligation::query()
            ->withoutGlobalScopes()
            ->with(['site', 'template'])
            ->where('status', Pending::$name)
            ->where('opens_at', '<=', $now)
            ->whereBetween('due_at', [$now, $hasta])
            ->oldest('due_at')
            ->limit((int) config('notifications.batch_limit', 500))
            ->get();

        foreach ($obligaciones as $obligacion) {
            $sede = $obligacion->site;
            $plantilla = $obligacion->template;

            // Sin sede o sin plantilla no hay a quien avisar ni de que. No
            // deberia pasar (las dos son claves foraneas obligatorias), pero un
            // aviso no es sitio para reventar.
            if (! $sede instanceof Site || ! $plantilla instanceof Template) {
                continue;
            }

            $zona = $sede->timezone;

            $mensaje = new NotificationMessage(
                topic: NotificationTopic::ObligationDueSoon,
                title: __('Pending: :template', ['template' => $plantilla->name]),
                body: __('«:template» of :site is due at :time.', [
                    'template' => $plantilla->name,
                    'site' => $sede->name,
                    'time' => $obligacion->due_at->setTimezone($zona)->format('H:i'),
                ]),
                url: ($this->url)('submissions.create', ['obligation' => $obligacion->getKey()]),
                meta: ['site' => $sede->name, 'obligation_id' => $obligacion->getKey()],
            );

            $mandados += ($this->notifyOnce)(
                $obligacion,
                NotificationTopic::ObligationDueSoon,
                0,
                $this->recipients->forObligation(Audience::Site, $obligacion),
                $mensaje,
                $now,
            ) ? 1 : 0;
        }

        return $mandados;
    }
}
