<?php

declare(strict_types=1);

namespace Ronda\Insights\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Recalcula `kpi_daily` para un rango de dias. RONDA-PLAN-MAESTRO.md sec. 9.6
 *
 * Recalcula en vez de acumular: una correccion de anteayer, una justificacion o
 * una aprobacion tardia cambian cifras de dias ya cerrados. Sumar incrementos
 * obligaria a saber que cambio y a deshacerlo bien; volver a contar el rango es
 * siempre correcto y cuesta dos consultas agregadas.
 *
 * Es idempotente: borra el rango y lo vuelve a escribir, todo en una
 * transaccion (regla 3). Si falla a medias, el tablero sigue viendo las cifras
 * anteriores en vez de la mitad de las nuevas.
 *
 * El dia es `obligations.occurrence_date`, que ya viene en el calendario de la
 * sede: aqui no se convierte ninguna zona horaria.
 */
final readonly class RecalculateKpis
{
    /**
     * Columnas y su valor por defecto. Todas las filas de un mismo `insert`
     * tienen que traer las mismas claves: sin esto, un dia con obligaciones
     * pero sin envios y otro con ambos irian en el mismo lote con distinto
     * numero de columnas.
     *
     * @var array<string, int>
     */
    private const array COUNTERS = [
        'fulfilled' => 0,
        'missed' => 0,
        'excused' => 0,
        'on_time' => 0,
        'late' => 0,
        'minutes_late_sum' => 0,
        'approved' => 0,
        'rejected' => 0,
        'approved_first_try' => 0,
        'reviews_resolved' => 0,
        'review_minutes_sum' => 0,
    ];

    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    /**
     * @return int cuantas filas de KPI quedaron escritas
     */
    public function __invoke(string $from, string $to): int
    {
        return $this->connection->transaction(function () use ($from, $to): int {
            $filas = $this->aggregate($from, $to);

            $this->connection->table('kpi_daily')->whereBetween('kpi_date', [$from, $to])->delete();

            if ($filas === []) {
                return 0;
            }

            $ahora = CarbonImmutable::now('UTC');

            foreach (array_chunk($filas, 500) as $lote) {
                $this->connection->table('kpi_daily')->insert(array_map(
                    static fn (array $fila): array => [
                        'kpi_date' => $fila['kpi_date'],
                        'site_id' => $fila['site_id'],
                        'template_id' => $fila['template_id'],
                        ...array_map(
                            static fn (string $columna): int => (int) ($fila[$columna] ?? 0),
                            array_combine(array_keys(self::COUNTERS), array_keys(self::COUNTERS)),
                        ),
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ],
                    $lote,
                ));
            }

            return count($filas);
        });
    }

    /**
     * Las dos mitades del calculo, fundidas por (dia, sede, plantilla):
     *
     *   - las obligaciones dicen que se pidio y como acabo (cumplimiento);
     *   - los envios dicen como llego y como se reviso (puntualidad, calidad,
     *     tiempo de revision).
     *
     * @return list<array<string, int|string>>
     */
    private function aggregate(string $from, string $to): array
    {
        $filas = [];

        foreach ($this->obligationCounts($from, $to) as $fila) {
            $filas[$this->key($fila)] = $fila;
        }

        foreach ($this->submissionCounts($from, $to) as $fila) {
            $clave = $this->key($fila);
            // Toda obligacion con envio esta en la primera consulta, asi que
            // aqui solo se completan columnas.
            $filas[$clave] = [...($filas[$clave] ?? []), ...$fila];
        }

        return array_values($filas);
    }

    /**
     * @param  array<string, int|string>  $fila
     */
    private function key(array $fila): string
    {
        return $fila['kpi_date'].'|'.$fila['site_id'].'|'.$fila['template_id'];
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function obligationCounts(string $from, string $to): array
    {
        $registros = $this->connection->table('obligations')
            ->selectRaw('occurrence_date::text as kpi_date, site_id, template_id')
            ->selectRaw("count(*) filter (where status = 'fulfilled') as fulfilled")
            ->selectRaw("count(*) filter (where status = 'missed') as missed")
            ->selectRaw("count(*) filter (where status = 'excused') as excused")
            ->whereBetween('occurrence_date', [$from, $to])
            ->groupBy('occurrence_date', 'site_id', 'template_id')
            ->get();

        return array_values($registros->map(static fn (object $fila): array => [
            'kpi_date' => (string) $fila->kpi_date,
            'site_id' => (int) $fila->site_id,
            'template_id' => (int) $fila->template_id,
            'fulfilled' => (int) $fila->fulfilled,
            'missed' => (int) $fila->missed,
            'excused' => (int) $fila->excused,
        ])->all());
    }

    /**
     * Los envios se atribuyen al dia de la obligacion que cumplieron, no al dia
     * en que se entregaron: un arqueo del martes entregado el miercoles a las
     * 00:10 sigue siendo el arqueo del martes.
     *
     * @return list<array<string, int|string>>
     */
    private function submissionCounts(string $from, string $to): array
    {
        $registros = $this->connection->table('submissions as s')
            ->join('obligations as o', 'o.id', '=', 's.obligation_id')
            ->selectRaw('o.occurrence_date::text as kpi_date, o.site_id, o.template_id')
            ->selectRaw('count(*) filter (where not s.is_late) as on_time')
            ->selectRaw('count(*) filter (where s.is_late) as late')
            ->selectRaw('coalesce(sum(s.minutes_late), 0) as minutes_late_sum')
            ->selectRaw("count(*) filter (where s.state = 'approved') as approved")
            ->selectRaw("count(*) filter (where s.state = 'rejected') as rejected")
            // A la primera: aprobado sin haber pasado por una correccion.
            ->selectRaw("count(*) filter (where s.state = 'approved' and s.revision = 1) as approved_first_try")
            ->selectRaw('count(*) filter (where s.reviewed_at is not null) as reviews_resolved')
            ->selectRaw('coalesce(sum(extract(epoch from (s.reviewed_at - s.submitted_at)) / 60)
                filter (where s.reviewed_at is not null), 0) as review_minutes_sum')
            ->whereBetween('o.occurrence_date', [$from, $to])
            ->groupBy('o.occurrence_date', 'o.site_id', 'o.template_id')
            ->get();

        return array_values($registros->map(static fn (object $fila): array => [
            'kpi_date' => (string) $fila->kpi_date,
            'site_id' => (int) $fila->site_id,
            'template_id' => (int) $fila->template_id,
            'on_time' => (int) $fila->on_time,
            'late' => (int) $fila->late,
            'minutes_late_sum' => (int) $fila->minutes_late_sum,
            'approved' => (int) $fila->approved,
            'rejected' => (int) $fila->rejected,
            'approved_first_try' => (int) $fila->approved_first_try,
            'reviews_resolved' => (int) $fila->reviews_resolved,
            'review_minutes_sum' => (int) round((float) $fila->review_minutes_sum),
        ])->all());
    }
}
