<?php

declare(strict_types=1);

namespace Ronda\Notifications\Application\Actions;

use Carbon\CarbonImmutable;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Notifications\Application\Data\NotificationMessage;
use Ronda\Notifications\Application\Queries\NotificationRecipientsQuery;
use Ronda\Notifications\Application\Queries\TenantUrlQuery;
use Ronda\Notifications\Domain\NotificationTopic;
use Ronda\Notifications\Domain\ValueObjects\EscalationLadder;
use Ronda\Notifications\Domain\ValueObjects\EscalationStage;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Scheduling\Domain\States\Missed;

/**
 * Sube por la escalera lo que no se entrego. RONDA-PLAN-MAESTRO.md sec. 9.4
 *
 *   al momento      -> la sede
 *   a las 2 horas   -> quien revisa esa sede
 *   al dia siguiente-> quien administra el cliente
 *
 * Los peldanos y sus tiempos estan en `config/notifications.php`. Cada peldano
 * se manda una sola vez (NotifyOnce), y si el job estuvo parado salen todos los
 * que tocaban, cada uno con su nivel.
 *
 * Solo mira lo incumplido hace poco: un incumplimiento de hace un mes ya no se
 * arregla avisando, y repasarlo entero cada hora haria la consulta inutilmente
 * cara.
 */
final readonly class EscalateMissedObligations
{
    /** Cuanto hacia atras se repasa. */
    private const int WINDOW_DAYS = 7;

    public function __construct(
        private NotificationRecipientsQuery $recipients,
        private NotifyOnce $notifyOnce,
        private TenantUrlQuery $url,
    ) {}

    /**
     * @return int cuantos avisos se mandaron
     */
    public function __invoke(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now('UTC');

        /** @var list<array{after_minutes?: int|string, audience?: string}> $config */
        $config = config('notifications.ladders.'.NotificationTopic::ObligationMissed->value, []);
        $escalera = EscalationLadder::fromConfig($config);

        $mandados = 0;

        $obligaciones = Obligation::query()
            ->withoutGlobalScopes()
            ->with(['site', 'template'])
            ->where('status', Missed::$name)
            ->where('closes_at', '>=', $now->subDays(self::WINDOW_DAYS))
            ->where('closes_at', '<=', $now)
            ->oldest('closes_at')
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
                topic: NotificationTopic::ObligationMissed,
                title: __('Not submitted: :template', ['template' => $plantilla->name]),
                body: __('«:template» of :site was due on :date at :time and was not submitted.', [
                    'template' => $plantilla->name,
                    'site' => $sede->name,
                    'date' => $obligacion->due_at->setTimezone($zona)->format('d/m'),
                    'time' => $obligacion->due_at->setTimezone($zona)->format('H:i'),
                ]),
                url: ($this->url)('submissions.pending'),
                meta: ['site' => $sede->name, 'obligation_id' => $obligacion->getKey()],
            );

            foreach ($escalera->stagesDue(CarbonImmutable::parse($obligacion->closes_at), $now) as $peldano) {
                /** @var EscalationStage $peldano */
                $mandados += ($this->notifyOnce)(
                    $obligacion,
                    NotificationTopic::ObligationMissed,
                    $peldano->level,
                    $this->recipients->forObligation($peldano->audience, $obligacion),
                    $mensaje,
                    $now,
                ) ? 1 : 0;
            }
        }

        return $mandados;
    }
}
