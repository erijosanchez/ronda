<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Application\Actions;

use Ronda\Scheduling\Domain\Models\Holiday;
use Ronda\Scheduling\Domain\Services\PeruvianHolidays;

/**
 * Garantiza que el calendario de feriados de un ano este sembrado.
 *
 * La provision siembra el ano en curso y el siguiente, pero un cliente dado de
 * alta hoy seguira en marcha dentro de tres anos. Materializar obligaciones de
 * un ano sin feriados cargados las generaria tambien en Navidad, asi que la
 * materializacion llama aqui antes de planificar.
 *
 * Idempotente, y no pisa nada: si el cliente corrigio o anadio un feriado a
 * mano, se respeta.
 */
final readonly class EnsureHolidays
{
    public function __construct(
        private PeruvianHolidays $calendar,
    ) {}

    public function __invoke(int $year): void
    {
        foreach ($this->calendar->forYear($year) as $feriado) {
            Holiday::query()->firstOrCreate(
                ['date' => $feriado['date'], 'scope' => 'national', 'region' => null],
                ['name' => $feriado['name'], 'source' => 'seed'],
            );
        }
    }
}
