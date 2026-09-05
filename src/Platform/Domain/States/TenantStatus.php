<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\States;

use Ronda\Platform\Domain\Models\Tenant;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Ciclo de vida de un tenant. RONDA-PLAN-MAESTRO.md sec. 7.2
 *
 *   trial -> active -> past_due -> suspended -> archived -> purged
 *
 * Las transiciones son declarativas a proposito (ADR 0007): el conjunto de
 * saltos legales se lee de un vistazo y no queda repartido en `if` por los
 * servicios, que es como reports-trimax perdio el control de sus estados.
 *
 * Suspension bloquea el acceso y conserva los datos. Archivado deja solo un
 * volcado cifrado. Purga destruye base y evidencia, y no tiene vuelta atras.
 *
 * @extends State<Tenant>
 */
abstract class TenantStatus extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Trial::class)
            // Alta y conversion.
            ->allowTransition(Trial::class, Active::class)
            ->allowTransition(Trial::class, Archived::class)
            // Impago: se avisa, se corta, se recupera.
            ->allowTransition(Active::class, PastDue::class)
            ->allowTransition(PastDue::class, Active::class)
            ->allowTransition(PastDue::class, Suspended::class)
            ->allowTransition(Suspended::class, Active::class)
            // Salida. Un tenant activo se suspende antes de archivarse: no se
            // archiva un cliente que todavia esta operando.
            ->allowTransition(Suspended::class, Archived::class)
            ->allowTransition(Archived::class, Purged::class);
    }
}
