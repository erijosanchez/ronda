<?php

declare(strict_types=1);

namespace Ronda\Workflow\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Ronda\Submissions\Domain\Models\Submission;
use Ronda\Submissions\Domain\States\Approved;
use Ronda\Submissions\Domain\States\Rejected;
use Ronda\Submissions\Domain\States\Submitted;
use Ronda\Submissions\Domain\States\UnderReview;

/**
 * La bandeja del supervisor. RONDA-PLAN-MAESTRO.md sec. 9.4
 *
 * Por defecto, lo que espera que alguien lo tome, lo mas antiguo primero: es lo
 * que mas se acerca a incumplir el plazo de revision.
 *
 * Solo envios de las sedes que el usuario alcanza: el filtro lo pone
 * AssignedSitesScope sobre `sites`, no esta pantalla. Pagina (regla 5).
 */
final class ReviewInbox extends Component
{
    use WithPagination;

    /** @var list<string> */
    private const array STATES = ['submitted', 'under_review', 'rejected', 'approved'];

    #[Url(except: 'submitted')]
    public string $state = 'submitted';

    #[Url(except: false)]
    public bool $onlyMine = false;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['state', 'onlyMine', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $this->authorize('viewInbox', Submission::class);

        // Un estado inventado en la URL no es un error del usuario: se vuelve a
        // la vista por defecto.
        if (! in_array($this->state, self::STATES, true)) {
            $this->state = Submitted::$name;
        }

        return view('workflow::inbox', [
            'submissions' => $this->submissions(),
            'states' => self::STATES,
            'counts' => $this->counts(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Submission>
     */
    private function submissions(): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->with(['site:id,name,timezone', 'templateVersion.template:id,name', 'author:id,name', 'reviewer:id,name'])
            ->where('state', $this->state)
            ->when($this->onlyMine && $this->state !== Submitted::$name, fn (Builder $q) => $q->where('reviewer_id', auth()->id()))
            ->when($this->search !== '', function (Builder $query): void {
                $termino = '%'.$this->search.'%';

                $query->where(function (Builder $q) use ($termino): void {
                    $q->whereHas('site', fn (Builder $s) => $s->where('name', 'ilike', $termino))
                        ->orWhereHas('templateVersion.template', fn (Builder $t) => $t->where('name', 'ilike', $termino));
                });
            })
            // Pendiente y en revision: lo mas viejo primero. Lo ya decidido: lo
            // mas reciente primero.
            ->when(
                in_array($this->state, [Submitted::$name, UnderReview::$name], true),
                fn (Builder $q) => $q->oldest('submitted_at'),
                fn (Builder $q) => $q->latest('reviewed_at'),
            )
            ->paginate(20);
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $conteos = [];

        foreach ([Submitted::$name, UnderReview::$name, Rejected::$name, Approved::$name] as $estado) {
            $conteos[$estado] = $this->baseQuery()->where('state', $estado)->count();
        }

        return $conteos;
    }

    /**
     * @return Builder<Submission>
     */
    private function baseQuery(): Builder
    {
        // La frontera por sede: whereHas sobre Site aplica su scope global.
        return Submission::query()->whereHas('site', fn (Builder $query): Builder => $query);
    }
}
