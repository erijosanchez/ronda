<?php

declare(strict_types=1);

use Ronda\Insights\Domain\ValueObjects\KpiSummary;

// La aritmetica de los indicadores. RONDA-PLAN-MAESTRO.md sec. 9.6
//
// Es donde es facil equivocarse y dificil darse cuenta: un cumplimiento que
// cuenta los justificados como incumplidos, o un 100 % cuando no habia nada que
// entregar, se leen igual de bien en pantalla y llevan a decisiones distintas.

it('calcula el cumplimiento sin contar lo justificado', function (): void {
    // 8 entregadas, 2 incumplidas y 5 justificadas: 80 %, no 53 %.
    $resumen = new KpiSummary(fulfilled: 8, missed: 2, excused: 5);

    expect($resumen->accountable())->toBe(10)
        ->and($resumen->compliance())->toBe(80.0);
})->group('insights');

it('distingue «no habia nada que medir» de cero', function (): void {
    $vacio = new KpiSummary;

    expect($vacio->compliance())->toBeNull()
        ->and($vacio->punctuality())->toBeNull()
        ->and($vacio->quality())->toBeNull()
        ->and($vacio->averageMinutesLate())->toBeNull()
        ->and($vacio->averageReviewMinutes())->toBeNull();

    // Y cero es cero: todo incumplido.
    expect(new KpiSummary(missed: 4)->compliance())->toBe(0.0);
})->group('insights');

it('mide la puntualidad sobre lo entregado y el retraso solo sobre lo tardio', function (): void {
    // 3 a tiempo, 1 tarde con 40 minutos: 75 % de puntualidad y 40 min de
    // retraso promedio (no 10, que saldria repartiendo entre las cuatro).
    $resumen = new KpiSummary(onTime: 3, late: 1, minutesLateSum: 40);

    expect($resumen->punctuality())->toBe(75.0)
        ->and($resumen->averageMinutesLate())->toBe(40.0);
})->group('insights');

it('mide la calidad sobre lo ya decidido, no sobre lo que sigue en revision', function (): void {
    // 6 aprobados (4 a la primera) y 2 rechazados: 4 de 8 resueltos.
    $resumen = new KpiSummary(approved: 6, rejected: 2, approvedFirstTry: 4);

    expect($resumen->quality())->toBe(50.0);
})->group('insights');

it('promedia el tiempo de revision', function (): void {
    $resumen = new KpiSummary(reviewsResolved: 4, reviewMinutesSum: 250);

    expect($resumen->averageReviewMinutes())->toBe(62.5);
})->group('insights');

it('suma dos periodos manteniendo los indicadores exactos', function (): void {
    // Sumar filas y dividir al final no es lo mismo que promediar porcentajes:
    // 1/1 y 1/9 no dan 55 %, dan 20 %.
    $total = new KpiSummary(fulfilled: 1, missed: 0)
        ->plus(new KpiSummary(fulfilled: 1, missed: 8));

    expect($total->compliance())->toBe(20.0);
})->group('insights');

it('lee una fila de la base tal como la devuelve la consulta', function (): void {
    $resumen = KpiSummary::fromRow([
        'fulfilled' => '9',
        'missed' => '1',
        'on_time' => '8',
        'late' => '1',
        'minutes_late_sum' => '15',
        'reviews_resolved' => null,
    ]);

    expect($resumen->fulfilled)->toBe(9)
        ->and($resumen->compliance())->toBe(90.0)
        ->and($resumen->averageMinutesLate())->toBe(15.0)
        ->and($resumen->averageReviewMinutes())->toBeNull();
})->group('insights');
