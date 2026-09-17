<?php

declare(strict_types=1);

namespace Ronda\Submissions\Presentation\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;
use Ronda\Scheduling\Domain\Models\Obligation;
use Ronda\Scheduling\Domain\States\Pending;

/**
 * La lista de pendientes de hoy. RONDA-PLAN-MAESTRO.md sec. 9.3
 *
 * «La pantalla del encargado deja de ser un formulario en blanco y pasa a ser
 * su lista de pendientes de hoy. Eso, por si solo, cambia la adopcion.»
 *
 * Muestra lo que se puede entregar AHORA o mas tarde hoy: pendiente, sin cerrar,
 * y que ya abrio o abre en las proximas horas. Solo de las sedes que el usuario
 * alcanza: el filtro lo pone AssignedSitesScope sobre `sites`, no esta pantalla.
 *
 * Pagina (regla 5).
 */
final class PendingObligations extends Component
{
    use WithPagination;

    public function render(): View
    {
        $this->authorize('viewAny', Obligation::class);

        return view('submissions::pending', [
            'obligations' => $this->obligations(),
            'now' => CarbonImmutable::now('UTC'),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Obligation>
     */
    private function obligations(): LengthAwarePaginator
    {
        $ahora = CarbonImmutable::now('UTC');

        return Obligation::query()
            ->with(['site', 'schedule.template'])
            ->where('status', Pending::$name)
            ->where('closes_at', '>=', $ahora)
            // Hasta el final del dia siguiente en UTC: cubre «mas tarde hoy» en
            // cualquier sede del continente sin traer la semana entera.
            ->where('opens_at', '<=', $ahora->addDay()->endOfDay())
            // La frontera por sede: whereHas sobre Site aplica su scope global.
            ->whereHas('site', fn (Builder $query): Builder => $query)
            ->oldest('due_at')
            ->paginate(20);
    }
}
