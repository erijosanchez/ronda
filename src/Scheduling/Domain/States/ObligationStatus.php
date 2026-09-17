<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Domain\States;

use Ronda\Scheduling\Domain\Models\Obligation;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Ciclo de vida de una obligacion. ADR 0007 y ADR 0008.
 *
 *   pending ──> fulfilled
 *      │
 *      ├──────> missed ──> excused
 *      │
 *      └──────> excused
 *
 * Maquina de estados declarativa y no enum, a diferencia de las plantillas:
 * aqui hay transiciones que NO deben ser posibles, y son justo las que
 * falsearian el KPI de cumplimiento.
 *
 * - `missed -> fulfilled` no existe. Una entrega fuera de plazo se registra con
 *   su retraso en el envio; reabrir la obligacion reescribiria el pasado y el
 *   cumplimiento de la semana cambiaria despues de haberse reportado.
 * - `missed -> excused` si existe: justificar un incumplimiento a posteriori
 *   (el local estuvo cerrado por una inundacion) es legitimo, y queda con motivo
 *   y responsable.
 * - Desde `fulfilled` y `excused` no se sale.
 *
 * @extends State<Obligation>
 */
abstract class ObligationStatus extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, Fulfilled::class)
            ->allowTransition(Pending::class, Missed::class)
            ->allowTransition(Pending::class, Excused::class)
            ->allowTransition(Missed::class, Excused::class);
    }

    /**
     * Si cuenta en el denominador del KPI de cumplimiento (ADR 0008):
     * fulfilled / (fulfilled + missed). Lo excusado y lo pendiente no cuentan.
     */
    public function countsTowardsCompliance(): bool
    {
        return false;
    }
}
