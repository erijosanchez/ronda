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
    /**
     * Como se llama este estado para quien lo lee.
     *
     * Vive en la clase base y no en la pantalla porque lo muestran ya dos —el
     * back-office y, cuando llegue, la ficha de facturacion— y un `match` por
     * pantalla acaba en dos listas que no dicen lo mismo.
     */
    public function label(): string
    {
        // `getMorphClass()` devuelve el nombre corto que declara cada estado
        // (`active`, `past_due`...), que es el mismo con el que se guarda en la
        // base. Leer `static::$name` a mano funcionaria, pero solo lo declaran
        // las subclases.
        return __('tenant-status.'.static::getMorphClass());
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Trial::class)
            // Alta y conversion.
            ->allowTransition(Trial::class, Active::class)
            ->allowTransition(Trial::class, Archived::class)
            // Una prueba que termina y cuyo primer cobro falla es, exactamente,
            // un cliente en mora: quiso pagar y no se pudo. Pasarlo antes por
            // `active` lo daria por convertido sin haber cobrado nunca, y eso
            // es lo que despues cuadra mal con la contabilidad.
            ->allowTransition(Trial::class, PastDue::class)
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
