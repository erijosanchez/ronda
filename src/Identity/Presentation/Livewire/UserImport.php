<?php

declare(strict_types=1);

namespace Ronda\Identity\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Ronda\Identity\Application\Actions\ImportUsers;
use Ronda\Identity\Application\Import\UserCsvPlanner;
use Ronda\Identity\Domain\Models\User;
use Ronda\Platform\Domain\Csv\CsvTable;
use Ronda\Platform\Domain\Exceptions\UnreadableCsv;
use Ronda\Platform\Presentation\Livewire\Concerns\ImportsCsv;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Importar personas desde un CSV. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Dos pasos, igual que las sedes: analizar no escribe nada, confirmar escribe
 * todo o nada.
 *
 * Nadie trae contrasena en el archivo. Quien entra nuevo la pone por «olvide
 * mi contrasena»; la pantalla lo dice antes de importar para que no sea una
 * sorpresa cuando llamen preguntando.
 */
final class UserImport extends Component
{
    use ImportsCsv;

    public function mount(): void
    {
        $this->authorize('create', User::class);
    }

    public function analizar(UserCsvPlanner $planner): void
    {
        $this->authorize('create', User::class);

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

    public function confirmar(UserCsvPlanner $planner, ImportUsers $import): void
    {
        $this->authorize('create', User::class);

        $tabla = $this->table();

        if (! $tabla instanceof CsvTable) {
            return;
        }

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

        $this->redirectRoute('users.index', navigate: true);
    }

    public function plantilla(): StreamedResponse
    {
        return $this->csvTemplate(
            'plantilla-personas.csv',
            ['nombre', 'correo', 'telefono', 'roles', 'estado', 'sedes'],
            ['Ana Quispe', 'ana.quispe@empresa.pe', '+51 999 888 777', 'Encargado', 'activo', 'LIM-001|LIM-002'],
        );
    }

    public function render(): View
    {
        return view('identity::users.import');
    }
}
