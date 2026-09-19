<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\ValueObjects;

/**
 * Los pasos que separan una cuenta recien creada de una que sirve.
 * RONDA-PLAN-MAESTRO.md sec. 15.3
 *
 * El orden es el orden: sin plantilla no hay que programar, sin sede no hay
 * donde, sin programacion no hay pendiente, y sin pendiente no hay primer
 * reporte. Cada paso solo tiene sentido cuando el anterior esta hecho.
 *
 * Ninguno guarda una bandera en la base: un paso esta hecho cuando existe lo
 * que produce. Asi no hay dos verdades que sincronizar, y una cuenta que borra
 * todas sus sedes vuelve a ver el paso que le falta.
 */
enum OnboardingStep: string
{
    case Templates = 'templates';
    case Sites = 'sites';
    case Team = 'team';
    case Schedules = 'schedules';
    case FirstReport = 'first_report';
}
