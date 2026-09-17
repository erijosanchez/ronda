<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Domain\ValueObjects;

use Carbon\CarbonImmutable;
use Ronda\Scheduling\Domain\Exceptions\InvalidRecurrence;

/**
 * Ventana de entrega en hora LOCAL de la sede. RONDA-PLAN-MAESTRO.md sec. 8.6
 *
 * «De 08:00 a 18:00» son las 08:00 del reloj de la sede, no de UTC ni del
 * servidor. La conversion a UTC se hace por sede, con su zona horaria.
 *
 * No cruza la medianoche. Una ventana de 22:00 a 06:00 pertenece a dos dias de
 * calendario y rompe la idea de «una obligacion por dia de sede» en la que
 * descansa la restriccion unica de `obligations`. Si aparece el caso, se modela
 * explicitamente en vez de dejarlo colar.
 */
final readonly class TimeWindow
{
    private function __construct(
        public string $start,
        public string $end,
    ) {}

    public static function between(string $start, string $end): self
    {
        $start = mb_substr($start, 0, 5);
        $end = mb_substr($end, 0, 5);

        if ($end <= $start) {
            throw InvalidRecurrence::windowEndsBeforeStart($start, $end);
        }

        return new self($start, $end);
    }

    /**
     * Los tres instantes de una obligacion, en UTC.
     *
     * @return array{opens_at: CarbonImmutable, due_at: CarbonImmutable, closes_at: CarbonImmutable}
     */
    public function instantsOn(string $date, string $timezone, int $toleranceMinutes): array
    {
        $abre = CarbonImmutable::parse("{$date} {$this->start}:00", $timezone)->utc();
        $vence = CarbonImmutable::parse("{$date} {$this->end}:00", $timezone)->utc();

        return [
            'opens_at' => $abre,
            'due_at' => $vence,
            'closes_at' => $vence->addMinutes($toleranceMinutes),
        ];
    }
}
