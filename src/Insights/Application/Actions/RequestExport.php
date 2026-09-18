<?php

declare(strict_types=1);

namespace Ronda\Insights\Application\Actions;

use Illuminate\Contracts\Bus\Dispatcher;
use Ronda\Identity\Domain\Models\User;
use Ronda\Insights\Application\Data\ExportRequest;
use Ronda\Insights\Application\Jobs\GenerateSubmissionExportJob;
use Ronda\Insights\Domain\ExportStatus;
use Ronda\Insights\Domain\Models\Export;

/**
 * Encarga una exportacion. RONDA-PLAN-MAESTRO.md sec. 13
 *
 * «Nunca en la peticion»: aqui solo se anota el encargo y se encola. Quien lo
 * pidio recibe un aviso cuando el archivo esta.
 *
 * Los filtros se guardan tal cual, incluidas las sedes que esa persona
 * alcanzaba: el job corre sin sesion, y congelarlas evita que un cambio de
 * permisos entre el encargo y la ejecucion ensanche lo exportado.
 */
final readonly class RequestExport
{
    public const string TYPE_SUBMISSIONS = 'submissions';

    public function __construct(
        private Dispatcher $bus,
    ) {}

    public function __invoke(ExportRequest $request, User $requester): Export
    {
        $export = Export::create([
            'type' => self::TYPE_SUBMISSIONS,
            'status' => ExportStatus::Queued->value,
            'filters' => $request->toArray(),
            'requested_by' => $requester->getKey(),
        ]);

        // A la cola `reports`, no a la de siempre: una exportacion de minutos
        // no puede retrasar el aviso de un SLA (sec. 13).
        $this->bus->dispatch(
            new GenerateSubmissionExportJob($export->id)->onQueue('reports'),
        );

        return $export;
    }
}
