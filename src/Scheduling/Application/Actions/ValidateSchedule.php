<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Application\Actions;

use Ronda\Forms\Domain\Models\Template;
use Ronda\Scheduling\Application\Data\ScheduleData;
use Ronda\Scheduling\Domain\Exceptions\InvalidSchedule;
use Ronda\Scheduling\Domain\ScheduleScope;
use Ronda\Scheduling\Domain\ValueObjects\Recurrence;
use Ronda\Scheduling\Domain\ValueObjects\TimeWindow;

/**
 * Comprueba que una programacion se sostiene antes de escribirla.
 *
 * Crear y editar pasan por aqui: si cada Action llevase su copia, la primera
 * regla nueva que se anadiese a una sola dejaria colar por la otra lo que la
 * primera rechaza.
 *
 * La regla y la ventana se validan construyendo sus value objects: una RRULE
 * que no se puede expandir guardada hoy es un job que revienta de madrugada
 * dentro de un mes.
 */
final readonly class ValidateSchedule
{
    public function __invoke(ScheduleData $data): void
    {
        Recurrence::fromString($data->rrule);
        TimeWindow::between($data->windowStart, $data->windowEnd);

        $this->guardTemplate($data->templateId);
        $this->guardScope($data);
    }

    /**
     * Solo se programa lo publicado: una plantilla en borrador no tiene version
     * con la que responder.
     */
    private function guardTemplate(int $templateId): void
    {
        $template = Template::query()->find($templateId);

        if (! $template instanceof Template || ! $template->status->canBeScheduled()) {
            throw InvalidSchedule::templateNotPublished();
        }
    }

    private function guardScope(ScheduleData $data): void
    {
        if ($data->scope === ScheduleScope::Zone && $data->zoneId === null) {
            throw InvalidSchedule::zoneRequired();
        }

        if ($data->scope === ScheduleScope::Sites && $data->siteIds === []) {
            throw InvalidSchedule::sitesRequired();
        }

        if ($data->toleranceMinutes < 0) {
            throw InvalidSchedule::negativeTolerance();
        }

        if ($data->endsOn !== null && $data->endsOn < $data->startsOn) {
            throw InvalidSchedule::endsBeforeStart();
        }
    }
}
