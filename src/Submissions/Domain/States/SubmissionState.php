<?php

declare(strict_types=1);

namespace Ronda\Submissions\Domain\States;

use Ronda\Submissions\Domain\Models\Submission;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Flujo de un envio. RONDA-PLAN-MAESTRO.md sec. 9.4 y ADR 0007.
 *
 *   Borrador --enviar--> Enviado --tomar--> En revision
 *                                               |--aprobar--> Aprobado (final)
 *                                               `--rechazar-> Rechazado
 *                                                                 |
 *                                                             corregir
 *                                                                 v
 *                                                              Enviado
 *
 * Es el flujo por defecto del plan. Las transiciones de revision las ejecutara
 * el modulo Workflow; hoy solo se usa la entrada en `submitted`. Se declaran
 * enteras ya porque son especificacion, no suposicion, y porque asi un envio no
 * puede acabar en un estado imposible aunque alguien lo toque a mano.
 *
 * El borrador existe en el flujo pero el servidor no lo guarda todavia: segun
 * la sec. 13.3 vive en el dispositivo (IndexedDB) hasta que se envia.
 *
 * @extends State<Submission>
 */
abstract class SubmissionState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Submitted::class)
            ->allowTransition(Submitted::class, UnderReview::class)
            ->allowTransition(UnderReview::class, Approved::class)
            ->allowTransition(UnderReview::class, Rejected::class)
            ->allowTransition(Rejected::class, Submitted::class);
    }
}
