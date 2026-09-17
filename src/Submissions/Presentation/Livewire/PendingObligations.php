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
use Ronda\Submissions\Domain\Models\Submission;
use Ronda\Submissions\Domain\States\Rejected;

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
            'toCorrect' => $this->toCorrect(),
            'now' => CarbonImmutable::now('UTC'),
        ]);
    }

    /**
     * Envios rechazados que esperan correccion. Tambien son pendientes: la
     * revision los devolvio y no avanzan hasta que la sede los arregle.
     *
     * Solo para quien puede entregar, y de las sedes que alcanza.
     *
     * @return LengthAwarePaginator<int, Submission>
     */
    private function toCorrect(): LengthAwarePaginator
    {
        $puedeEntregar = auth()->user()?->can('create', Submission::class) ?? false;

        return Submission::query()
            ->with(['site:id,name', 'templateVersion.template:id,name'])
            ->where('state', Rejected::$name)
            ->unless($puedeEntregar, fn (Builder $query): Builder => $query->whereKey([]))
            ->whereHas('site', fn (Builder $query): Builder => $query)
            ->latest('reviewed_at')
            ->paginate(10, pageName: 'corregir');
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
