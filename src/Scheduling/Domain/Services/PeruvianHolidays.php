<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Domain\Services;

use Carbon\CarbonImmutable;

/**
 * Feriados nacionales del Peru para un ano. RONDA-PLAN-MAESTRO.md sec. 2.2
 *
 * ATENCION: es una SEMILLA, no la fuente oficial. El calendario lo fija la ley
 * y el Ejecutivo anade dias no laborables por decreto con poca antelacion, asi
 * que esta lista tiene que contrastarse con El Peruano antes de produccion y
 * reemplazarse por una sincronizacion oficial (la columna `holidays.source`
 * existe para eso). Los mas recientes —7 de junio, 23 de julio, 6 de agosto y 9
 * de diciembre— son los que primero conviene verificar.
 *
 * Solo nacionales. Los regionales dependen de la region de cada sede, que
 * todavia no se modela.
 *
 * La Semana Santa se calcula con el algoritmo gregoriano anonimo
 * (Meeus/Jones/Butcher) y no con `easter_days()`, que exige la extension
 * `calendar` y no esta en la imagen.
 */
final class PeruvianHolidays
{
    /**
     * Feriados de fecha fija: mes-dia => nombre.
     *
     * @var array<string, string>
     */
    private const array FIXED = [
        '01-01' => 'Año Nuevo',
        '05-01' => 'Día del Trabajo',
        '06-07' => 'Batalla de Arica y Día de la Bandera',
        '06-29' => 'San Pedro y San Pablo',
        '07-23' => 'Día de la Fuerza Aérea del Perú',
        '07-28' => 'Fiestas Patrias',
        '07-29' => 'Fiestas Patrias',
        '08-06' => 'Batalla de Junín',
        '08-30' => 'Santa Rosa de Lima',
        '10-08' => 'Combate de Angamos',
        '11-01' => 'Todos los Santos',
        '12-08' => 'Inmaculada Concepción',
        '12-09' => 'Batalla de Ayacucho',
        '12-25' => 'Navidad',
    ];

    /**
     * @return list<array{date: string, name: string}> ordenados por fecha
     */
    public function forYear(int $year): array
    {
        $feriados = [];

        foreach (self::FIXED as $mesDia => $nombre) {
            $feriados[] = ['date' => "{$year}-{$mesDia}", 'name' => $nombre];
        }

        $pascua = $this->easterSunday($year);
        $feriados[] = ['date' => $pascua->subDays(3)->toDateString(), 'name' => 'Jueves Santo'];
        $feriados[] = ['date' => $pascua->subDays(2)->toDateString(), 'name' => 'Viernes Santo'];

        usort($feriados, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        return $feriados;
    }

    /**
     * Domingo de Pascua en el calendario gregoriano.
     */
    public function easterSunday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::parse(sprintf('%04d-%02d-%02d', $year, $mes, $dia), 'UTC');
    }
}
