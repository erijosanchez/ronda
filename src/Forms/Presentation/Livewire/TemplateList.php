<?php

declare(strict_types=1);

namespace Ronda\Forms\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Ronda\Forms\Domain\Models\Template;

/**
 * Listado de plantillas. RONDA-PLAN-MAESTRO.md sec. 9.2
 *
 * Pagina siempre (regla 5).
 */
final class TemplateList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorize('viewAny', Template::class);

        return view('forms::templates.index', [
            'templates' => $this->templates(),
            'canManage' => auth()->user()?->can('create', Template::class) ?? false,
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Template>
     */
    private function templates(): LengthAwarePaginator
    {
        return Template::query()
            ->with('currentVersion')
            ->withCount('versions')
            ->when($this->search !== '', function ($query): void {
                $termino = '%'.$this->search.'%';

                $query->where(function ($q) use ($termino): void {
                    $q->where('name', 'ilike', $termino)
                        ->orWhere('code', 'ilike', $termino);
                });
            })
            ->orderBy('name')
            ->paginate(20);
    }
}
