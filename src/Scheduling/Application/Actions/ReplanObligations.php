<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Ronda\Scheduling\Application\Jobs\MaterializeObligationsJob;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Scheduling\Domain\Models\Schedule;
use Ronda\Scheduling\Domain\States\Pending;

/**
 * Pone las obligaciones futuras de una programacion al dia con lo que la
 * programacion dice AHORA. ADR 0008.
 *
 * Las obligaciones son una foto, y eso protege el pasado: lo cumplido, lo
 * incumplido y lo excusado no se toca nunca, y tampoco una entrega que ya esta
 * abierta, que una sede puede estar llenando en este momento.
 *
 * Lo que todavia no abrio no es historia, es un plan. Si la programacion se
 * pausa o cambia de sedes, de dias o de horario, dejar esas filas como estaban
 * seria seguir pidiendo durante dos semanas lo que ya no se pide. Por eso se
 * descartan y se vuelven a materializar sobre el mismo horizonte que el job.
 *
 * No borra nada que tenga un envio: solo filas `pending`, y un envio pasa la
 * obligacion a `fulfilled` en la misma transaccion en que se crea.
 */
final readonly class ReplanObligations
{
    public function __construct(
        private ConnectionInterface $connection,
        private MaterializeObligations $materialize,
    ) {}

    /**
     * @return int cuantas obligaciones quedaron materializadas de nuevo
     */
    public function __invoke(Schedule $schedule, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now('UTC');

        return $this->connection->transaction(function () use ($schedule, $now): int {
            Obligation::query()
                ->where('schedule_id', $schedule->getKey())
                ->where('status', Pending::$name)
                ->where('opens_at', '>', $now)
                ->delete();

            if (! $schedule->active) {
                return 0;
            }

            // El mismo horizonte que el job, con su dia de margen hacia atras
            // (ver MaterializeObligationsJob). Lo que ya cerro no se crea.
            return ($this->materialize)(
                $schedule,
                $now->subDay()->toDateString(),
                $now->addDays(MaterializeObligationsJob::HORIZON_DAYS)->toDateString(),
                notClosedBefore: $now,
            );
        });
    }
}
