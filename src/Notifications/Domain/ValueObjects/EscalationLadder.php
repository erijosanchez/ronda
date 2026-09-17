<?php

declare(strict_types=1);

namespace Ronda\Notifications\Domain\ValueObjects;

use Carbon\CarbonImmutable;
use Ronda\Notifications\Domain\Audience;
use Ronda\Notifications\Domain\Exceptions\InvalidEscalation;

/**
 * «A las N horas avisa al responsable; a las N+M, al jefe de zona; a las N+2M,
 * a gerencia». RONDA-PLAN-MAESTRO.md sec. 9.4
 *
 * Una escalera es una lista de peldanos ordenados por tiempo desde el hecho
 * (que venciera una entrega, que un envio entrara en la bandeja). Cada peldano
 * dice a quien avisar cuando ese tiempo ya paso.
 *
 * Es pura: no sabe de correos ni de usuarios. Solo responde «a estas alturas,
 * que peldanos tocaban». Quien ya se aviso lo recuerda `sla_events`, no esto:
 * asi la escalera puede cambiarse sin reescribir el historial.
 */
final readonly class EscalationLadder
{
    /**
     * @param  list<EscalationStage>  $stages  ordenados por afterMinutes
     */
    private function __construct(
        public array $stages,
    ) {}

    /**
     * @param  list<array{after_minutes?: int|string, audience?: string}>  $config
     */
    public static function fromConfig(array $config): self
    {
        $peldanos = [];

        foreach (array_values($config) as $nivel => $peldano) {
            $audiencia = Audience::tryFrom((string) ($peldano['audience'] ?? ''));

            if (! $audiencia instanceof Audience) {
                throw InvalidEscalation::unknownAudience((string) ($peldano['audience'] ?? ''));
            }

            $minutos = (int) ($peldano['after_minutes'] ?? 0);

            if ($minutos < 0) {
                throw InvalidEscalation::negativeDelay($minutos);
            }

            $peldanos[] = new EscalationStage($nivel, $minutos, $audiencia);
        }

        // Ordenados por tiempo, conservando el nivel que les dio la
        // configuracion: el nivel es lo que se guarda en `sla_events`, y
        // reordenarlo cambiaria de sitio avisos ya mandados.
        usort($peldanos, static fn (EscalationStage $a, EscalationStage $b): int => $a->afterMinutes <=> $b->afterMinutes);

        return new self($peldanos);
    }

    /**
     * Los peldanos que ya tocaban a `$now`, contados desde `$since`.
     *
     * Devuelve TODOS los vencidos y no solo el ultimo: si el job no corrio en
     * cuatro horas, los avisos que se saltaron salen igual, cada uno con su
     * nivel. Los repetidos los filtra `sla_events`.
     *
     * @return list<EscalationStage>
     */
    public function stagesDue(CarbonImmutable $since, CarbonImmutable $now): array
    {
        $minutos = $since->diffInMinutes($now, false);

        return array_values(array_filter(
            $this->stages,
            static fn (EscalationStage $stage): bool => $minutos >= $stage->afterMinutes,
        ));
    }
}
