<?php

declare(strict_types=1);

namespace Ronda\Directory\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Ronda\Directory\Application\Actions\DeleteZone;
use Ronda\Directory\Domain\Exceptions\CannotDeleteZone;
use Ronda\Directory\Domain\Models\Zone;

/**
 * Listado de zonas. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Valida, invoca la Action y devuelve (regla 1). Borrar se hace desde aqui
 * porque es la accion mas frecuente despues de crear, y la Action es quien
 * decide si se puede.
 *
 * Pagina (regla 5).
 */
final class ZoneList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $zoneId): void
    {
        $zone = Zone::query()->findOrFail($zoneId);
        $this->authorize('delete', $zone);

        try {
            resolve(DeleteZone::class)($zone);
        } catch (CannotDeleteZone $e) {
            $this->addError('zone', $e->getMessage());

            return;
        }

        session()->flash('status', __('Zone deleted.'));
    }

    public function render(): View
    {
        $this->authorize('viewAny', Zone::class);

        return view('directory::zones.index', [
            'zones' => $this->zones(),
            'canManage' => auth()->user()?->can('create', Zone::class) ?? false,
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Zone>
     */
    private function zones(): LengthAwarePaginator
    {
        return Zone::query()
            ->with(['parent:id,name', 'manager:id,name'])
            ->withCount(['sites', 'children'])
            ->when($this->search !== '', fn ($query) => $query->where('name', 'ilike', '%'.$this->search.'%'))
            ->orderBy('name')
            ->paginate(20);
    }
}
