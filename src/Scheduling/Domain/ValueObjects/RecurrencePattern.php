<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Domain\ValueObjects;

use Ronda\Scheduling\Domain\Exceptions\InvalidRecurrence;
use Ronda\Scheduling\Domain\RecurrenceFrequency;

/**
 * Una recurrencia contada como la cuenta una persona. RONDA-PLAN-MAESTRO.md
 * sec. 9.1
 *
 * La base guarda una RRULE y es lo que expande el motor (Recurrence). Pero
 * pedirle a quien administra una cadena `FREQ=WEEKLY;BYDAY=MO,WE` es pedirle
 * que se equivoque. Este objeto es el puente en los dos sentidos:
 *
 * - `toRecurrence()` arma la regla desde lo que se eligio en pantalla.
 * - `fromRecurrence()` reconoce una regla guardada para volver a mostrarla con
 *   los mismos controles. Lo que no reconoce lo trata como regla
 *   personalizada: nunca la reinterpreta, porque reescribir una regla que no se
 *   entiende del todo cambiaria las fechas sin que nadie lo pidiera.
 */
final readonly class RecurrencePattern
{
    /**
     * Dias de la semana en el orden en que se muestran y se escriben en BYDAY.
     */
    public const array WEEKDAYS = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];

    /**
     * Valor de BYMONTHDAY para «el ultimo dia del mes». Un «dia 31» se salta
     * los meses cortos; esto no.
     */
    public const int LAST_DAY_OF_MONTH = -1;

    /**
     * @param  list<string>  $weekdays  solo para `Weekly`, en el orden de WEEKDAYS
     */
    private function __construct(
        public RecurrenceFrequency $frequency,
        public int $interval = 1,
        public array $weekdays = [],
        public ?int $monthDay = null,
        public ?string $customRule = null,
    ) {}

    public static function daily(int $interval = 1): self
    {
        return new self(RecurrenceFrequency::Daily, self::guardInterval($interval));
    }

    /**
     * @param  list<string>  $weekdays
     */
    public static function weekly(array $weekdays, int $interval = 1): self
    {
        $weekdays = array_map(mb_strtoupper(...), $weekdays);

        foreach ($weekdays as $day) {
            if (! in_array($day, self::WEEKDAYS, true)) {
                throw InvalidRecurrence::unknownWeekday($day);
            }
        }

        // Se reordenan y se quitan repetidos: la regla guardada no depende del
        // orden en que se marcaron las casillas.
        $ordenados = array_values(array_intersect(self::WEEKDAYS, $weekdays));

        if ($ordenados === []) {
            throw InvalidRecurrence::noWeekdays();
        }

        return new self(RecurrenceFrequency::Weekly, self::guardInterval($interval), $ordenados);
    }

    public static function monthly(int $monthDay, int $interval = 1): self
    {
        if ($monthDay !== self::LAST_DAY_OF_MONTH && ($monthDay < 1 || $monthDay > 31)) {
            throw InvalidRecurrence::invalidMonthDay($monthDay);
        }

        return new self(RecurrenceFrequency::Monthly, self::guardInterval($interval), monthDay: $monthDay);
    }

    public static function custom(string $rule): self
    {
        return new self(RecurrenceFrequency::Custom, customRule: Recurrence::fromString($rule)->rule);
    }

    /**
     * Reconoce las reglas que la pantalla sabe construir. Cualquier otra cosa
     * (BYSETPOS, COUNT, BYMONTH, un BYDAY con ordinal...) queda como regla
     * personalizada, tal cual.
     */
    public static function fromRecurrence(Recurrence $recurrence): self
    {
        $partes = [];

        foreach (explode(';', $recurrence->rule) as $parte) {
            if ($parte === '') {
                continue;
            }

            [$clave, $valor] = array_pad(explode('=', $parte, 2), 2, '');
            $partes[$clave] = $valor;
        }

        $intervalo = $partes['INTERVAL'] ?? '1';
        unset($partes['INTERVAL']);

        if (! ctype_digit($intervalo) || (int) $intervalo < 1) {
            return self::custom($recurrence->rule);
        }

        $intervalo = (int) $intervalo;
        $claves = array_keys($partes);
        sort($claves);

        return match (true) {
            $partes === ['FREQ' => 'DAILY'] => self::daily($intervalo),

            $claves === ['BYDAY', 'FREQ'] && $partes['FREQ'] === 'WEEKLY'
                && array_diff(explode(',', $partes['BYDAY']), self::WEEKDAYS) === [] => self::weekly(explode(',', $partes['BYDAY']), $intervalo),

            $claves === ['BYMONTHDAY', 'FREQ'] && $partes['FREQ'] === 'MONTHLY'
                && self::isMonthDay($partes['BYMONTHDAY']) => self::monthly((int) $partes['BYMONTHDAY'], $intervalo),

            default => self::custom($recurrence->rule),
        };
    }

    public function toRecurrence(): Recurrence
    {
        $intervalo = $this->interval > 1 ? ';INTERVAL='.$this->interval : '';

        return Recurrence::fromString(match ($this->frequency) {
            RecurrenceFrequency::Daily => 'FREQ=DAILY'.$intervalo,
            RecurrenceFrequency::Weekly => 'FREQ=WEEKLY;BYDAY='.implode(',', $this->weekdays).$intervalo,
            RecurrenceFrequency::Monthly => 'FREQ=MONTHLY;BYMONTHDAY='.$this->monthDay.$intervalo,
            RecurrenceFrequency::Custom => (string) $this->customRule,
        });
    }

    /**
     * La regla en una frase, para listados.
     */
    public function describe(): string
    {
        return match ($this->frequency) {
            RecurrenceFrequency::Daily => $this->interval === 1
                ? __('Every day')
                : __('Every :n days', ['n' => $this->interval]),

            RecurrenceFrequency::Weekly => $this->describeWeekly(),

            RecurrenceFrequency::Monthly => $this->describeMonthly(),

            RecurrenceFrequency::Custom => __('Custom rule: :rule', ['rule' => (string) $this->customRule]),
        };
    }

    private function describeWeekly(): string
    {
        if ($this->weekdays === self::WEEKDAYS && $this->interval === 1) {
            return __('Every day');
        }

        $dias = implode(', ', array_map(
            static fn (string $day): string => __('weekdays.'.$day),
            $this->weekdays,
        ));

        return $this->interval === 1
            ? __('Every week: :days', ['days' => $dias])
            : __('Every :n weeks: :days', ['n' => $this->interval, 'days' => $dias]);
    }

    private function describeMonthly(): string
    {
        if ($this->monthDay === self::LAST_DAY_OF_MONTH) {
            return $this->interval === 1
                ? __('Last day of every month')
                : __('Every :n months, on the last day', ['n' => $this->interval]);
        }

        return $this->interval === 1
            ? __('Day :day of every month', ['day' => (int) $this->monthDay])
            : __('Every :n months, on day :day', ['n' => $this->interval, 'day' => (int) $this->monthDay]);
    }

    private static function guardInterval(int $interval): int
    {
        if ($interval < 1) {
            throw InvalidRecurrence::invalidInterval($interval);
        }

        return $interval;
    }

    private static function isMonthDay(string $value): bool
    {
        if ($value === (string) self::LAST_DAY_OF_MONTH) {
            return true;
        }

        return ctype_digit($value) && (int) $value >= 1 && (int) $value <= 31;
    }
}
