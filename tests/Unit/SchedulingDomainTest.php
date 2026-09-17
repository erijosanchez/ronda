<?php

declare(strict_types=1);

use Ronda\Scheduling\Domain\Exceptions\InvalidRecurrence;
use Ronda\Scheduling\Domain\Services\ObligationPlanner;
use Ronda\Scheduling\Domain\Services\PeruvianHolidays;
use Ronda\Scheduling\Domain\ValueObjects\PlannedObligation;
use Ronda\Scheduling\Domain\ValueObjects\Recurrence;
use Ronda\Scheduling\Domain\ValueObjects\SiteCalendar;
use Ronda\Scheduling\Domain\ValueObjects\TimeWindow;

// La logica de calendario del motor. ADR 0008.
//
// Pura: sin base de datos. Aqui estan los errores caros —un feriado que no se
// salta, un «todos los lunes» que cae en domingo, una hora de vencimiento
// desplazada cinco horas— y por eso se prueba a fondo.
/**
 * @param  list<SiteCalendar>  $sites
 * @param  list<string>  $holidays
 * @return list<PlannedObligation>
 */
function planificar(
    string $rrule,
    array $sites,
    string $from,
    string $to,
    array $holidays = [],
    bool $skipHolidays = true,
    string $startsOn = '2026-01-01',
    ?string $endsOn = null,
    string $windowStart = '08:00',
    string $windowEnd = '18:00',
    int $tolerance = 0,
): array {
    return (new ObligationPlanner)->plan(
        recurrence: Recurrence::fromString($rrule),
        window: TimeWindow::between($windowStart, $windowEnd),
        toleranceMinutes: $tolerance,
        skipHolidays: $skipHolidays,
        startsOn: $startsOn,
        endsOn: $endsOn,
        sites: $sites,
        holidays: $holidays,
        from: $from,
        to: $to,
    );
}

function sedeLima(int $id = 1, ?string $desde = null, ?string $hasta = null): SiteCalendar
{
    return new SiteCalendar($id, 'America/Lima', $desde, $hasta);
}

// --- Recurrencia ------------------------------------------------------------

it('expande una regla de dias laborables', function (): void {
    // Semana del lunes 7 al domingo 13 de septiembre de 2026.
    $fechas = Recurrence::fromString('FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR')
        ->datesBetween('2026-09-07', '2026-09-07', '2026-09-13');

    expect($fechas)->toBe(['2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11']);
});

it('respeta el intervalo contado desde el inicio, no desde el rango pedido', function (): void {
    // Cada dos semanas desde el lunes 7. Pidiendo desde el 14, la semana del
    // 14 NO toca: el intervalo se cuenta desde el ancla.
    $fechas = Recurrence::fromString('FREQ=WEEKLY;INTERVAL=2;BYDAY=MO')
        ->datesBetween('2026-09-07', '2026-09-14', '2026-09-30');

    expect($fechas)->toBe(['2026-09-21']);
});

it('resuelve el ultimo viernes de cada mes', function (): void {
    $fechas = Recurrence::fromString('FREQ=MONTHLY;BYDAY=FR;BYSETPOS=-1')
        ->datesBetween('2026-01-01', '2026-09-01', '2026-11-30');

    expect($fechas)->toBe(['2026-09-25', '2026-10-30', '2026-11-27']);
});

it('rechaza una regla que no se puede interpretar', function (): void {
    expect(fn (): Recurrence => Recurrence::fromString('FREQ=CADA_TANTO'))
        ->toThrow(InvalidRecurrence::class);
});

it('rechaza frecuencias menores que un dia', function (): void {
    // Una obligacion es una entrega por dia de sede.
    expect(fn (): Recurrence => Recurrence::fromString('FREQ=HOURLY'))
        ->toThrow(InvalidRecurrence::class, 'menor que un dia');
});

it('rechaza DTSTART dentro de la regla', function (): void {
    expect(fn (): Recurrence => Recurrence::fromString('DTSTART:20260101T000000Z RRULE:FREQ=DAILY'))
        ->toThrow(InvalidRecurrence::class, 'DTSTART');
});

// --- Ventana y zona horaria --------------------------------------------------

it('convierte la ventana local de la sede a UTC', function (): void {
    // Lima es UTC-5 todo el ano: 08:00 local son las 13:00 UTC.
    $instantes = TimeWindow::between('08:00', '18:00')->instantsOn('2026-09-15', 'America/Lima', 30);

    expect($instantes['opens_at']->toIso8601String())->toBe('2026-09-15T13:00:00+00:00')
        ->and($instantes['due_at']->toIso8601String())->toBe('2026-09-15T23:00:00+00:00')
        ->and($instantes['closes_at']->toIso8601String())->toBe('2026-09-15T23:30:00+00:00');
});

it('rechaza una ventana que termina antes de empezar', function (): void {
    expect(fn (): TimeWindow => TimeWindow::between('18:00', '08:00'))
        ->toThrow(InvalidRecurrence::class);
});

// --- Feriados ---------------------------------------------------------------

it('calcula el domingo de Pascua de anos conocidos', function (): void {
    $calendario = new PeruvianHolidays;

    // Fechas verificables en cualquier calendario.
    expect($calendario->easterSunday(2024)->toDateString())->toBe('2024-03-31')
        ->and($calendario->easterSunday(2025)->toDateString())->toBe('2025-04-20')
        ->and($calendario->easterSunday(2026)->toDateString())->toBe('2026-04-05')
        ->and($calendario->easterSunday(2027)->toDateString())->toBe('2027-03-28');
});

it('incluye Jueves y Viernes Santo moviles', function (): void {
    $fechas = array_column((new PeruvianHolidays)->forYear(2026), 'name', 'date');

    expect($fechas['2026-04-02'] ?? null)->toBe('Jueves Santo')
        ->and($fechas['2026-04-03'] ?? null)->toBe('Viernes Santo')
        ->and($fechas['2026-07-28'] ?? null)->toBe('Fiestas Patrias')
        ->and($fechas['2026-12-25'] ?? null)->toBe('Navidad');
});

// --- Planificador -----------------------------------------------------------

it('genera una obligacion por sede y por dia que toca', function (): void {
    $plan = planificar('FREQ=DAILY', [sedeLima(1), sedeLima(2)], '2026-09-15', '2026-09-16');

    expect($plan)->toHaveCount(4)
        ->and(array_map(fn (PlannedObligation $o): string => $o->siteId.'@'.$o->occurrenceDate, $plan))
        ->toBe(['1@2026-09-15', '2@2026-09-15', '1@2026-09-16', '2@2026-09-16']);
});

it('salta los feriados cuando la programacion lo pide', function (): void {
    $plan = planificar('FREQ=DAILY', [sedeLima()], '2026-07-27', '2026-07-30', holidays: ['2026-07-28', '2026-07-29']);

    expect(array_map(fn (PlannedObligation $o): string => $o->occurrenceDate, $plan))->toBe(['2026-07-27', '2026-07-30']);
});

it('no salta los feriados cuando la programacion no lo pide', function (): void {
    // Un local de 24 horas reporta tambien en Fiestas Patrias.
    $plan = planificar('FREQ=DAILY', [sedeLima()], '2026-07-27', '2026-07-30', holidays: ['2026-07-28'], skipHolidays: false);

    expect($plan)->toHaveCount(4);
});

it('no genera obligaciones antes de que la sede abra ni despues de cerrar', function (): void {
    // Sin esto el KPI se llena de incumplimientos de locales que no existian.
    $plan = planificar('FREQ=DAILY', [sedeLima(1, '2026-09-16', '2026-09-17')], '2026-09-15', '2026-09-18');

    expect(array_map(fn (PlannedObligation $o): string => $o->occurrenceDate, $plan))->toBe(['2026-09-16', '2026-09-17']);
});

it('respeta la vigencia de la propia programacion', function (): void {
    $plan = planificar('FREQ=DAILY', [sedeLima()], '2026-09-01', '2026-09-30', startsOn: '2026-09-10', endsOn: '2026-09-12');

    expect(array_map(fn (PlannedObligation $o): string => $o->occurrenceDate, $plan))->toBe(['2026-09-10', '2026-09-11', '2026-09-12']);
});

it('devuelve nada si el rango pedido queda fuera de la vigencia', function (): void {
    $plan = planificar('FREQ=DAILY', [sedeLima()], '2026-01-01', '2026-01-31', startsOn: '2026-09-01');

    expect($plan)->toBe([]);
});

it('aplica la tolerancia al cierre y no al vencimiento', function (): void {
    [$obligacion] = planificar('FREQ=DAILY', [sedeLima()], '2026-09-15', '2026-09-15', tolerance: 45);

    // Pasado el vencimiento es tardia; pasado el cierre, incumplida.
    expect($obligacion->dueAt->toIso8601String())->toBe('2026-09-15T23:00:00+00:00')
        ->and($obligacion->closesAt->toIso8601String())->toBe('2026-09-15T23:45:00+00:00');
});

it('usa la zona horaria de cada sede, no una comun', function (): void {
    $bogota = new SiteCalendar(2, 'America/Bogota');   // UTC-5
    $madrid = new SiteCalendar(3, 'Europe/Madrid');    // UTC+2 en septiembre

    $plan = planificar('FREQ=DAILY', [sedeLima(1), $bogota, $madrid], '2026-09-15', '2026-09-15');

    $abre = array_map(fn (PlannedObligation $o): string => $o->opensAt->format('H:i'), $plan);

    // Las 08:00 de cada reloj local, que en UTC no coinciden.
    expect($abre)->toBe(['13:00', '13:00', '06:00']);
});
