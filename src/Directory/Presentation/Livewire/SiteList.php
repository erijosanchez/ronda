<?php

declare(strict_types=1);

namespace Ronda\Directory\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Ronda\Directory\Domain\Models\Site;

/**
 * Listado de sedes. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * No filtra por usuario: de eso se encarga AssignedSitesScope, que el modelo
 * lleva puesto. Aqui solo se ordena, se busca y se pagina.
 *
 * Pagina siempre (regla 5). Un cliente con trescientas sedes tumbaria la
 * pantalla con un `all()`.
 */
final class SiteList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: false)]
    public bool $onlyActive = false;

    public function updatedSearch(): void
    {
        // Al cambiar el filtro hay que volver a la primera pagina, o el usuario
        // se queda mirando una pagina 7 que ya no existe.
        $this->resetPage();
    }

    public function updatedOnlyActive(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorize('viewAny', Site::class);

        return view('directory::sites.index', [
            'sites' => $this->sites(),
            // La vista no decide permisos ni conoce nombres de clase: recibe
            // la respuesta ya tomada.
            'canCreate' => auth()->user()?->can('create', Site::class) ?? false,
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Site>
     */
    private function sites(): LengthAwarePaginator
    {
        return Site::query()
            ->with('zone')
            ->when($this->onlyActive, fn ($query) => $query->active())
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
