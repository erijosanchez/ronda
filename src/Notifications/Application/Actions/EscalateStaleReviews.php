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
use Ronda\Submissions\Domain\Models\Submission;
use Ronda\Submissions\Domain\States\Submitted;

/**
 * El otro plazo del SLA: el de revisar. RONDA-PLAN-MAESTRO.md sec. 9.4
 *
 * «El SLA se evalua en dos puntos: plazo de entrega (obligacion) y plazo de
 * revision (bandeja del supervisor).» Un envio que nadie toma no es culpa de la
 * sede, y sin esto se queda en la bandeja indefinidamente.
 *
 * Cuenta desde la ultima vez que el envio entro en la bandeja, no desde
 * `submitted_at`: una correccion conserva la fecha de entrega original (mide la
 * puntualidad de la sede) pero reinicia la espera de la revision.
 *
 * Por eso el nivel del aviso lleva sumada la revision: un envio corregido puede
 * volver a escalar, y `sla_events` no lo confunde con el aviso de antes.
 */
final readonly class EscalateStaleReviews
{
    /**
     * Hueco de niveles que se reserva a cada revision del envio. Con esto, la
     * correccion numero 2 escala en los niveles 100, 101... sin chocar con los
     * avisos de la primera entrega.
     */
    private const int LEVELS_PER_REVISION = 100;

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
        $config = config('notifications.ladders.'.NotificationTopic::ReviewOverdue->value, []);
        $escalera = EscalationLadder::fromConfig($config);

        if ($escalera->stages === []) {
            return 0;
        }

        $primerPeldano = $escalera->stages[0]->afterMinutes;
        $mandados = 0;

        $envios = Submission::query()
            ->with(['site', 'templateVersion.template'])
            ->where('state', Submitted::$name)
            ->where('updated_at', '<=', $now->subMinutes($primerPeldano))
            ->oldest('updated_at')
            ->limit((int) config('notifications.batch_limit', 500))
            ->get();

        foreach ($envios as $envio) {
            $sede = $envio->site;
            $plantilla = $envio->templateVersion?->template;

            if (! $sede instanceof Site || ! $plantilla instanceof Template) {
                continue;
            }

            $zona = $sede->timezone;

            $mensaje = new NotificationMessage(
                topic: NotificationTopic::ReviewOverdue,
                title: __('Waiting for review: :template', ['template' => $plantilla->name]),
                body: __('«:template» of :site has been waiting for review since :date.', [
                    'template' => $plantilla->name,
                    'site' => $sede->name,
                    'date' => $envio->updated_at->setTimezone($zona)->format('d/m H:i'),
                ]),
                url: ($this->url)('submissions.show', ['submission' => $envio->getKey()]),
                meta: ['site' => $sede->name, 'submission_id' => $envio->getKey()],
            );

            $desde = CarbonImmutable::parse($envio->updated_at);

            foreach ($escalera->stagesDue($desde, $now) as $peldano) {
                /** @var EscalationStage $peldano */
                $mandados += ($this->notifyOnce)(
                    $envio,
                    NotificationTopic::ReviewOverdue,
                    $peldano->level + ($envio->revision - 1) * self::LEVELS_PER_REVISION,
                    $this->recipients->forSubmission($peldano->audience, $envio),
                    $mensaje,
                    $now,
                ) ? 1 : 0;
            }
        }

        return $mandados;
    }
}
