<?php

declare(strict_types=1);

namespace Ronda\Insights\Domain\ValueObjects;

/**
 * Los indicadores de un periodo, ya sumados. RONDA-PLAN-MAESTRO.md sec. 9.6
 *
 * Las sumas vienen de `kpi_daily`; aqui solo se hacen las divisiones. Estan en
 * un objeto puro y no en la consulta porque son el sitio donde es facil
 * equivocarse y dificil darse cuenta: un cumplimiento que cuenta los
 * justificados como incumplidos, o un 100 % cuando no habia nada que entregar.
 *
 * Un indicador sin base devuelve `null`, no cero: «no habia nada que medir» y
 * «se incumplio todo» no son lo mismo, y pintarlos igual lleva a decisiones
 * equivocadas. La vista los distingue con un guion.
 */
final readonly class KpiSummary
{
    public function __construct(
        public int $fulfilled = 0,
        public int $missed = 0,
        public int $excused = 0,
        public int $onTime = 0,
        public int $late = 0,
        public int $minutesLateSum = 0,
        public int $approved = 0,
        public int $rejected = 0,
        public int $approvedFirstTry = 0,
        public int $reviewsResolved = 0,
        public int $reviewMinutesSum = 0,
    ) {}

    /**
     * @param  array<string, int|string|null>  $row
     */
    public static function fromRow(array $row): self
    {
        $numero = static fn (string $clave): int => (int) ($row[$clave] ?? 0);

        return new self(
            fulfilled: $numero('fulfilled'),
            missed: $numero('missed'),
            excused: $numero('excused'),
            onTime: $numero('on_time'),
            late: $numero('late'),
            minutesLateSum: $numero('minutes_late_sum'),
            approved: $numero('approved'),
            rejected: $numero('rejected'),
            approvedFirstTry: $numero('approved_first_try'),
            reviewsResolved: $numero('reviews_resolved'),
            reviewMinutesSum: $numero('review_minutes_sum'),
        );
    }

    /**
     * Lo que se esperaba y se pudo cumplir o incumplir. Los justificados no
     * cuentan: una sede cerrada por feriado no incumplio nada (sec. 9.1).
     */
    public function accountable(): int
    {
        return $this->fulfilled + $this->missed;
    }

    /**
     * `fulfilled / (fulfilled + missed)`, en porcentaje. Null si no habia nada
     * que entregar.
     */
    public function compliance(): ?float
    {
        return $this->percentage($this->fulfilled, $this->accountable());
    }

    /**
     * Porcentaje de entregas dentro de la ventana, sobre las entregadas.
     */
    public function punctuality(): ?float
    {
        return $this->percentage($this->onTime, $this->onTime + $this->late);
    }

    /**
     * Porcentaje aprobado a la primera, sobre lo ya resuelto. Lo que sigue en
     * revision no cuenta: todavia no se sabe como acabara.
     */
    public function quality(): ?float
    {
        return $this->percentage($this->approvedFirstTry, $this->approved + $this->rejected);
    }

    /**
     * Minutos de retraso promedio, contando SOLO las entregas tardias: mezclar
     * las puntuales diluye el numero hasta volverlo inutil.
     */
    public function averageMinutesLate(): ?float
    {
        return $this->late === 0 ? null : round($this->minutesLateSum / $this->late, 1);
    }

    /**
     * Minutos promedio entre la entrega y su decision.
     */
    public function averageReviewMinutes(): ?float
    {
        return $this->reviewsResolved === 0
            ? null
            : round($this->reviewMinutesSum / $this->reviewsResolved, 1);
    }

    public function plus(self $other): self
    {
        return new self(
            fulfilled: $this->fulfilled + $other->fulfilled,
            missed: $this->missed + $other->missed,
            excused: $this->excused + $other->excused,
            onTime: $this->onTime + $other->onTime,
            late: $this->late + $other->late,
            minutesLateSum: $this->minutesLateSum + $other->minutesLateSum,
            approved: $this->approved + $other->approved,
            rejected: $this->rejected + $other->rejected,
            approvedFirstTry: $this->approvedFirstTry + $other->approvedFirstTry,
            reviewsResolved: $this->reviewsResolved + $other->reviewsResolved,
            reviewMinutesSum: $this->reviewMinutesSum + $other->reviewMinutesSum,
        );
    }

    private function percentage(int $parte, int $total): ?float
    {
        return $total === 0 ? null : round($parte * 100 / $total, 1);
    }
}
