<?php

declare(strict_types=1);

namespace Ronda\Notifications\Domain;

/**
 * A quien le toca un aviso. RONDA-PLAN-MAESTRO.md sec. 9.4
 *
 * No son roles: son capacidades sobre la sede. Quien puede entregar ahi, quien
 * puede revisar ahi. Se resuelven con las Policies (regla 4), asi que un
 * cliente que reorganice sus roles sigue avisando a quien corresponde.
 */
enum Audience: string
{
    /** Quien entrega en esa sede. */
    case Site = 'site';

    /** Quien revisa envios de esa sede. */
    case Reviewers = 'reviewers';

    /** Quien administra el cliente: el ultimo peldano de la escalera. */
    case Administrators = 'administrators';
}
