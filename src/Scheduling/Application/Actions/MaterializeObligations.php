<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Scheduling\Domain\Models\Holiday;
use Ronda\Scheduling\Domain\Models\Schedule;
use Ronda\Scheduling\Domain\ScheduleScope;
use Ronda\Scheduling\Domain\Services\ObligationPlanner;
use Ronda\Scheduling\Domain\States\Pending;
use Ronda\Scheduling\Domain\ValueObjects\PlannedObligation;
use Ronda\Scheduling\Domain\ValueObjects\SiteCalendar;

/**
 * Materializa por adelantado las obligaciones de una programacion. ADR 0008.
 *
 * Es IDEMPOTENTE: la restriccion unica (schedule, site, occurrence_date) y
 * `insertOrIgnore` hacen que reejecutarla sobre el mismo rango no duplique ni
 * pise nada. Importa porque corre cada dia sobre un horizonte que se solapa con
 * el del dia anterior, y porque un job que falla a medias tiene que poder
 * relanzarse sin pensar.
 *
 * Tampoco toca obligaciones ya creadas. Son una foto: si la programacion cambio,
 * las de dias ya materializados conservan sus instantes originales.
 */
final readonly class MaterializeObligations
{
    public function __construct(
        private ConnectionInterface $connection,
        private ObligationPlanner $planner,
        private EnsureHolidays $ensureHolidays,
    ) {}

    /**
     * @param  CarbonImmutable|null  $notClosedBefore  descarta las ocurrencias
     *                                                 cuyo cierre ya paso en ese instante. Lo usa quien crea o cambia
     *                                                 una programacion a media tarde: sin esto, la entrega de esa
     *                                                 misma manana naceria vencida y contaria como incumplida sin
     *                                                 que la sede llegara a verla.
     * @return int cuantas obligaciones nuevas se crearon
     */
    public function __invoke(Schedule $schedule, string $from, string $to, ?CarbonImmutable $notClosedBefore = null): int
    {
        if (! $schedule->active) {
            return 0;
        }

        foreach (range((int) mb_substr($from, 0, 4), (int) mb_substr($to, 0, 4)) as $year) {
            ($this->ensureHolidays)($year);
        }

        $plan = $this->planner->plan(
            recurrence: $schedule->recurrence(),
            window: $schedule->window(),
            toleranceMinutes: $schedule->tolerance_minutes,
            skipHolidays: $schedule->skip_holidays,
            startsOn: $schedule->starts_on->toDateString(),
            endsOn: $schedule->ends_on?->toDateString(),
            sites: $this->sitesFor($schedule),
            holidays: $this->holidaysBetween($from, $to),
            from: $from,
            to: $to,
        );

        if ($notClosedBefore instanceof CarbonImmutable) {
            $plan = array_values(array_filter(
                $plan,
                static fn (PlannedObligation $o): bool => $o->closesAt->greaterThan($notClosedBefore),
            ));
        }

        if ($plan === []) {
            return 0;
        }

        $ahora = CarbonImmutable::now('UTC');

        $filas = array_map(static fn (PlannedObligation $o): array => [
            'schedule_id' => $schedule->getKey(),
            'site_id' => $o->siteId,
            'template_id' => $schedule->template_id,
            'occurrence_date' => $o->occurrenceDate,
            'opens_at' => $o->opensAt,
            'due_at' => $o->dueAt,
            'closes_at' => $o->closesAt,
            'status' => Pending::$name,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ], $plan);

        // Por lotes: catorce dias sobre trescientas sedes son miles de filas, y
        // un solo INSERT choca con el limite de parametros de PostgreSQL.
        return $this->connection->transaction(function () use ($filas): int {
            $creadas = 0;

            foreach (array_chunk($filas, 500) as $lote) {
                $creadas += $this->connection->table('obligations')->insertOrIgnore($lote);
            }

            return $creadas;
        });
    }

    /**
     * Las sedes a las que alcanza la programacion, SIN el scope de frontera.
     *
     * Esto corre en un job sin sesion, y AssignedSitesScope no filtra sin
     * usuario. Aun asi se quita explicitamente: la materializacion tiene que
     * cubrir todo el parque siempre, y no depender de que un job no herede nunca
     * una sesion.
     *
     * @return list<SiteCalendar>
     */
    private function sitesFor(Schedule $schedule): array
    {
        $query = Site::query()->withoutGlobalScopes()->whereNull('deleted_at');

        $sites = match ($schedule->scope) {
            ScheduleScope::AllSites => $query->get(),
            ScheduleScope::Zone => $query->where('zone_id', $schedule->zone_id)->get(),
            ScheduleScope::Sites => $query->whereIn('id', $schedule->sites()->withoutGlobalScopes()->pluck('sites.id'))->get(),
        };

        $calendarios = [];

        foreach ($sites as $site) {
            $calendarios[] = new SiteCalendar(
                siteId: $site->id,
                timezone: $site->timezone,
                activeFrom: $site->active_from?->toDateString(),
                activeUntil: $site->active_until?->toDateString(),
            );
        }

        return $calendarios;
    }

    /**
     * @return list<string>
     */
    private function holidaysBetween(string $from, string $to): array
    {
        $fechas = [];

        foreach (Holiday::query()->where('scope', 'national')->whereBetween('date', [$from, $to])->get(['date']) as $holiday) {
            /** @var Carbon $fecha */
            $fecha = $holiday->date;
            $fechas[] = $fecha->toDateString();
        }

        return $fechas;
    }
}
