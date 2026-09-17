<?php

declare(strict_types=1);

use Ronda\Scheduling\Domain\Exceptions\InvalidRecurrence;
use Ronda\Scheduling\Domain\RecurrenceFrequency;
use Ronda\Scheduling\Domain\ValueObjects\Recurrence;
use Ronda\Scheduling\Domain\ValueObjects\RecurrencePattern;

// El puente entre los controles de la pantalla y la RRULE que guarda la base.
//
// El error caro aqui es silencioso: una regla guardada que, al abrirla para
// editar, se reinterpreta como otra distinta y cambia los dias de entrega sin
// que nadie lo haya pedido.

it('arma la regla de cada forma de repeticion', function (RecurrencePattern $pattern, string $rule): void {
    expect($pattern->toRecurrence()->rule)->toBe($rule);
})->with([
    'diaria' => [fn (): RecurrencePattern => RecurrencePattern::daily(), 'FREQ=DAILY'],
    'cada 2 dias' => [fn (): RecurrencePattern => RecurrencePattern::daily(2), 'FREQ=DAILY;INTERVAL=2'],
    'semanal' => [fn (): RecurrencePattern => RecurrencePattern::weekly(['MO', 'WE', 'FR']), 'FREQ=WEEKLY;BYDAY=MO,WE,FR'],
    'quincenal' => [fn (): RecurrencePattern => RecurrencePattern::weekly(['TU'], 2), 'FREQ=WEEKLY;BYDAY=TU;INTERVAL=2'],
    'mensual' => [fn (): RecurrencePattern => RecurrencePattern::monthly(15), 'FREQ=MONTHLY;BYMONTHDAY=15'],
    'fin de mes' => [fn (): RecurrencePattern => RecurrencePattern::monthly(-1), 'FREQ=MONTHLY;BYMONTHDAY=-1'],
    'trimestral' => [fn (): RecurrencePattern => RecurrencePattern::monthly(1, 3), 'FREQ=MONTHLY;BYMONTHDAY=1;INTERVAL=3'],
])->group('scheduling');

it('ordena los dias y quita repetidos sin importar como se marcaron', function (): void {
    $pattern = RecurrencePattern::weekly(['fr', 'MO', 'FR', 'we']);

    expect($pattern->weekdays)->toBe(['MO', 'WE', 'FR'])
        ->and($pattern->toRecurrence()->rule)->toBe('FREQ=WEEKLY;BYDAY=MO,WE,FR');
})->group('scheduling');

it('reconoce una regla guardada y la devuelve identica', function (string $rule, RecurrenceFrequency $frequency): void {
    $pattern = RecurrencePattern::fromRecurrence(Recurrence::fromString($rule));

    expect($pattern->frequency)->toBe($frequency)
        ->and($pattern->toRecurrence()->rule)->toBe($rule);
})->with([
    ['FREQ=DAILY', RecurrenceFrequency::Daily],
    ['FREQ=DAILY;INTERVAL=3', RecurrenceFrequency::Daily],
    ['FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR', RecurrenceFrequency::Weekly],
    ['FREQ=WEEKLY;BYDAY=SA;INTERVAL=2', RecurrenceFrequency::Weekly],
    ['FREQ=MONTHLY;BYMONTHDAY=-1', RecurrenceFrequency::Monthly],
    ['FREQ=MONTHLY;BYMONTHDAY=10;INTERVAL=6', RecurrenceFrequency::Monthly],
])->group('scheduling');

it('deja como personalizada, sin tocarla, la regla que no sabe mostrar', function (string $rule): void {
    $pattern = RecurrencePattern::fromRecurrence(Recurrence::fromString($rule));

    expect($pattern->frequency)->toBe(RecurrenceFrequency::Custom)
        ->and($pattern->toRecurrence()->rule)->toBe($rule);
})->with([
    'primer lunes' => 'FREQ=MONTHLY;BYDAY=1MO',
    'con COUNT' => 'FREQ=DAILY;COUNT=10',
    'semanal sin dias' => 'FREQ=WEEKLY',
    'anual' => 'FREQ=YEARLY;BYMONTH=12;BYMONTHDAY=31',
    'ultimo dia habil' => 'FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1',
])->group('scheduling');

it('rechaza lo que no forma una repeticion', function (Closure $build): void {
    expect($build)->toThrow(InvalidRecurrence::class);
})->with([
    'semana sin dias' => fn (): RecurrencePattern => RecurrencePattern::weekly([]),
    'dia inventado' => fn (): RecurrencePattern => RecurrencePattern::weekly(['XX']),
    'dia del mes 0' => fn (): RecurrencePattern => RecurrencePattern::monthly(0),
    'dia del mes 32' => fn (): RecurrencePattern => RecurrencePattern::monthly(32),
    'intervalo 0' => fn (): RecurrencePattern => RecurrencePattern::daily(0),
    'regla rota' => fn (): RecurrencePattern => RecurrencePattern::custom('FREQ=NUNCA'),
    'por horas' => fn (): RecurrencePattern => RecurrencePattern::custom('FREQ=HOURLY'),
])->group('scheduling');

it('describe la regla en una frase', function (): void {
    app()->setLocale('es');

    expect(RecurrencePattern::daily()->describe())->toBe('Todos los días')
        ->and(RecurrencePattern::daily(2)->describe())->toBe('Cada 2 días')
        ->and(RecurrencePattern::weekly(['MO', 'WE'])->describe())->toBe('Cada semana: lun, mié')
        ->and(RecurrencePattern::weekly(RecurrencePattern::WEEKDAYS)->describe())->toBe('Todos los días')
        ->and(RecurrencePattern::monthly(-1)->describe())->toBe('El último día de cada mes')
        ->and(RecurrencePattern::monthly(15, 2)->describe())->toBe('Cada 2 meses, el día 15');
})->group('scheduling');

it('muestra las proximas fechas desde un dia, contando desde el inicio', function (): void {
    // Quincenal desde el martes 1 de septiembre: el 8 no toca, el 15 si.
    $fechas = RecurrencePattern::weekly(['TU'], 2)
        ->toRecurrence()
        ->nextDates('2026-09-01', '2026-09-08', 3);

    expect($fechas)->toBe(['2026-09-15', '2026-09-29', '2026-10-13']);
})->group('scheduling');
