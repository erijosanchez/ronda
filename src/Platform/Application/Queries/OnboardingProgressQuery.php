<?php

declare(strict_types=1);

namespace Ronda\Platform\Application\Queries;

use Ronda\Directory\Domain\Models\Site;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Identity\Domain\Models\User;
use Ronda\Platform\Domain\ValueObjects\OnboardingProgress;
use Ronda\Platform\Domain\ValueObjects\OnboardingStep;
use Ronda\Scheduling\Domain\Models\Schedule;
use Ronda\Submissions\Domain\Models\Submission;

/**
 * Que le falta a este cliente para estar en marcha.
 * RONDA-PLAN-MAESTRO.md sec. 15.3
 *
 * Cinco `exists()`, uno por paso: preguntar por la existencia y no por el total
 * deja que PostgreSQL pare en la primera fila.
 *
 * Corre siempre en el contexto del tenant, asi que lo que cuenta es lo suyo. La
 * unica consulta con frontera por sede es la de `sites`, y quien ve esta
 * pantalla tiene `site.manage`, que alcanza el parque entero; para cualquier
 * otro la pantalla no se abre.
 */
final readonly class OnboardingProgressQuery
{
    public function __invoke(): OnboardingProgress
    {
        return new OnboardingProgress([
            OnboardingStep::Templates->value => Template::query()->exists(),
            OnboardingStep::Sites->value => Site::query()->exists(),
            // Mas de uno: la duena se cuenta a si misma desde el alta, y
            // trabajar sola no es haber invitado a nadie.
            OnboardingStep::Team->value => User::query()->count() > 1,
            OnboardingStep::Schedules->value => Schedule::query()->exists(),
            OnboardingStep::FirstReport->value => Submission::query()->exists(),
        ]);
    }
}
