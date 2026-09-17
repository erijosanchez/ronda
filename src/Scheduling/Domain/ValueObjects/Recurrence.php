<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Domain\ValueObjects;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Ronda\Scheduling\Domain\Exceptions\InvalidRecurrence;
use RRule\RRule;

/**
 * Cuando se repite una entrega. RONDA-PLAN-MAESTRO.md sec. 9.1
 *
 * Una regla RFC 5545 (RRULE) sin DTSTART. La expansion la hace
 * `rlanvin/php-rrule`: la RFC tiene suficientes esquinas (BYSETPOS, BYDAY con
 * ordinal, dias 31 en meses cortos) como para no reescribirla.
 *
 * Trabaja con FECHAS, no con instantes. Que dia toca la entrega se decide en el
 * calendario de la sede; a que hora UTC vence se decide despues, con su zona
 * horaria. Mezclar las dos cosas es como un «todos los lunes» acaba cayendo en
 * domingo para una sede cinco horas al oeste.
 */
final readonly class Recurrence
{
    /**
     * Frecuencias menores que un dia. Una obligacion es una entrega por dia de
     * sede (la restriccion unica de `obligations` lo garantiza), asi que no
     * tienen sentido aqui.
     */
    private const array SUB_DAILY = ['HOURLY', 'MINUTELY', 'SECONDLY'];

    private function __construct(
        public string $rule,
    ) {}

    public static function fromString(string $rule): self
    {
        $rule = mb_strtoupper(trim($rule));

        if (str_contains($rule, 'DTSTART')) {
            throw InvalidRecurrence::containsStart();
        }

        // Se valida construyendola: si la libreria no la entiende, tampoco la
        // entendera al expandir, y es mejor saberlo al guardar.
        try {
            $parsed = new RRule($rule, '2000-01-01');
        } catch (InvalidArgumentException $e) {
            throw InvalidRecurrence::unparseable($rule, $e->getMessage());
        }

        $frequency = (string) ($parsed->getRule()['FREQ'] ?? '');

        if (in_array($frequency, self::SUB_DAILY, true)) {
            throw InvalidRecurrence::subDaily($frequency);
        }

        return new self($rule);
    }

    /**
     * Fechas en las que toca entrega entre dos dias, ambos incluidos.
     *
     * @param  string  $anchor  primer dia de la programacion (Y-m-d); la regla
     *                          cuenta intervalos y COUNT desde aqui, no desde
     *                          $from
     * @return list<string> fechas Y-m-d, ordenadas
     */
    public function datesBetween(string $anchor, string $from, string $to): array
    {
        // Se ancla a medianoche UTC y se leen solo las fechas: la zona horaria
        // no interviene en QUE dia toca.
        $rrule = new RRule($this->rule, new DateTimeImmutable($anchor.' 00:00:00', new DateTimeZone('UTC')));

        $desde = CarbonImmutable::parse($from.' 00:00:00', 'UTC');
        $hasta = CarbonImmutable::parse($to.' 23:59:59', 'UTC');

        $fechas = [];

        foreach ($rrule->getOccurrencesBetween($desde, $hasta) as $occurrence) {
            $fechas[] = $occurrence->format('Y-m-d');
        }

        return $fechas;
    }

    /**
     * Las primeras fechas en las que toca entrega a partir de un dia, incluido.
     * Sirve para que quien programa vea lo que ha escrito antes de guardarlo.
     *
     * @param  string  $anchor  primer dia de la programacion (Y-m-d)
     * @param  string  $from  primer dia que interesa (Y-m-d)
     * @return list<string> fechas Y-m-d, ordenadas
     */
    public function nextDates(string $anchor, string $from, int $limit): array
    {
        $rrule = new RRule($this->rule, new DateTimeImmutable($anchor.' 00:00:00', new DateTimeZone('UTC')));

        $fechas = [];

        foreach ($rrule->getOccurrencesAfter(CarbonImmutable::parse($from.' 00:00:00', 'UTC'), true, $limit) as $occurrence) {
            $fechas[] = $occurrence->format('Y-m-d');
        }

        return $fechas;
    }
}
