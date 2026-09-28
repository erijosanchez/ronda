<?php

declare(strict_types=1);

namespace Ronda\Directory\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Ronda\Directory\Application\Data\PlannedSite;
use Ronda\Directory\Application\Data\SiteImportPlan;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Platform\Domain\Csv\ImportResult;
use Ronda\Platform\Domain\Exceptions\InvalidImportPlan;

/**
 * Escribe un plan de sedes ya validado. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Todo o nada, en una sola transaccion (regla 3): importar 300 sedes y que
 * fallen las tres ultimas no puede dejar 297 dentro. Con el cliente mirando la
 * pantalla, media estructura cargada es peor que ninguna, porque nadie sabe
 * por donde iba.
 *
 * Solo acepta planes sin problemas. Quien lo llama ya se los enseno al
 * usuario; volver a comprobarlo aqui es lo que impide que una pantalla nueva
 * —o la API de manana— se salte el paso.
 *
 * Las sedes que ya existen se ACTUALIZAN en vez de duplicarse. Subir dos veces
 * el mismo archivo es lo normal: alguien corrige una celda y reintenta.
 */
final readonly class ImportSites
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    /**
     * @throws InvalidImportPlan
     */
    public function __invoke(SiteImportPlan $plan): ImportResult
    {
        if (! $plan->isValid()) {
            throw InvalidImportPlan::withIssues(count($plan->issues));
        }

        return $this->connection->transaction(function () use ($plan): ImportResult {
            $creadas = 0;
            $actualizadas = 0;

            foreach ($plan->sites as $planificada) {
                $planificada->isUpdate()
                    ? $actualizadas += $this->update($planificada)
                    : $creadas += $this->create($planificada);
            }

            return new ImportResult($creadas, $actualizadas);
        });
    }

    private function create(PlannedSite $planned): int
    {
        Site::create($planned->data->toAttributes());

        return 1;
    }

    private function update(PlannedSite $planned): int
    {
        // Sin el scope de frontera: quien importa administra la estructura
        // entera, y una sede que no tiene asignada no puede volverse invisible
        // justo cuando la esta corrigiendo.
        $sede = Site::withoutGlobalScopes()->find($planned->existingId);

        if (! $sede instanceof Site) {
            return 0;
        }

        $sede->update($planned->data->toAttributes());

        return 1;
    }
}
