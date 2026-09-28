<?php

declare(strict_types=1);

namespace Ronda\Directory\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Ronda\Directory\Application\Actions\ImportSites;
use Ronda\Directory\Application\Import\SiteCsvPlanner;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Platform\Domain\Csv\CsvTable;
use Ronda\Platform\Domain\Exceptions\UnreadableCsv;
use Ronda\Platform\Presentation\Livewire\Concerns\ImportsCsv;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Importar sedes desde un CSV. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Dos pasos, siempre: analizar y confirmar. El primero no escribe nada y dice
 * exactamente que va a pasar; el segundo escribe todo o nada. Un cliente que
 * llega con ochenta locales en una hoja no puede descubrir a mitad de camino
 * que la columna de zonas estaba mal.
 *
 * Valida, invoca la Action y devuelve (regla 1): quien decide si una fila vale
 * es el planificador, y quien escribe es ImportSites.
 */
final class SiteImport extends Component
{
    use ImportsCsv;

    public function mount(): void
    {
        $this->authorize('create', Site::class);
    }

    public function analizar(SiteCsvPlanner $planner): void
    {
        $this->authorize('create', Site::class);

        $tabla = $this->table();

        if (! $tabla instanceof CsvTable) {
            return;
        }

        // Al archivo le pueden faltar columnas enteras: eso no es una fila
        // mala, es que no hay nada que analizar.
        try {
            $plan = $planner($tabla);
        } catch (UnreadableCsv $e) {
            $this->addError('file', $this->describeRejection($e));

            return;
        }

        $this->analyzed = true;
        $this->toCreate = $plan->toCreate();
        $this->toUpdate = $plan->toUpdate();
        $this->issues = $this->describeIssues($plan->issues);
    }

    public function confirmar(SiteCsvPlanner $planner, ImportSites $import): void
    {
        $this->authorize('create', Site::class);

        $tabla = $this->table();

        if (! $tabla instanceof CsvTable) {
            return;
        }

        // Se vuelve a planificar contra la base de ahora, no contra el analisis
        // de hace un rato: entre los dos pasos alguien pudo crear una sede con
        // el mismo codigo.
        try {
            $plan = $planner($tabla);
        } catch (UnreadableCsv $e) {
            $this->addError('file', $this->describeRejection($e));

            return;
        }

        if (! $plan->isValid()) {
            $this->analyzed = true;
            $this->toCreate = $plan->toCreate();
            $this->toUpdate = $plan->toUpdate();
            $this->issues = $this->describeIssues($plan->issues);

            return;
        }

        $resultado = $import($plan);

        $this->reset('file', 'analyzed', 'toCreate', 'toUpdate', 'issues');

        session()->flash('status', __(':created new, :updated updated.', [
            'created' => $resultado->created,
            'updated' => $resultado->updated,
        ]));

        $this->redirectRoute('sites.index', navigate: true);
    }

    public function plantilla(): StreamedResponse
    {
        return $this->csvTemplate(
            'plantilla-sedes.csv',
            ['codigo', 'nombre', 'zona', 'direccion', 'latitud', 'longitud', 'zona_horaria', 'abre', 'cierra'],
            ['LIM-001', 'Miraflores', 'Lima Centro', 'Av. Larco 123', '-12.1211', '-77.0296', 'America/Lima', '08:00', '22:00'],
        );
    }

    public function render(): View
    {
        return view('directory::sites.import');
    }
}
