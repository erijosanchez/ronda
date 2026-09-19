<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Contracts;

use Ronda\Platform\Domain\Models\Plan;
use Ronda\Platform\Domain\PlanFeature;
use Ronda\Platform\Domain\ValueObjects\PlanLimits;

/**
 * Que puede hacer el cliente en curso, segun su plan.
 *
 * Es un contrato del dominio y no una consulta suelta porque quien lo pregunta
 * son Actions de OTROS modulos (crear una plantilla, guardar evidencia), y la
 * regla de dependencia no les deja llamar a la capa de aplicacion de Platform:
 * solo a su dominio. La implementacion, que si lee la base central, vive en
 * Infrastructure.
 *
 * Tambien es lo que hace comprobables los limites: una prueba sustituye esta
 * pieza por un doble y no necesita un plan en la base.
 */
interface PlanProvider
{
    public function limits(): PlanLimits;

    public function allows(PlanFeature $feature): bool;

    /**
     * El plan contratado, o null si el cliente todavia no tiene ninguno.
     *
     * Lo necesita la pantalla que lo muestra: para decidir bastan los limites,
     * pero para explicarle a alguien por que no puede crear la cuarta
     * plantilla hace falta el nombre del plan y su precio.
     */
    public function current(): ?Plan;
}
