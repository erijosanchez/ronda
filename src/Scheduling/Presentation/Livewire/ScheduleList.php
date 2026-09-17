<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Ronda\Scheduling\Application\Actions\PauseSchedule;
use Ronda\Scheduling\Application\Actions\ResumeSchedule;
use Ronda\Scheduling\Domain\Models\Schedule;

/**
 * Listado de programaciones. RONDA-PLAN-MAESTRO.md sec. 9.1
 *
 * Desde aqui se pausa y se reanuda: es la accion mas frecuente despues de
 * crear (una campana que termina, un local en obras) y no merece abrir el
 * formulario.
 *
 * Pagina siempre (regla 5).
 */
final class ScheduleList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: false)]
    public bool $onlyActive = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOnlyActive(): void
    {
        $this->resetPage();
    }

    public function pause(int $scheduleId): void
    {
        $schedule = Schedule::query()->findOrFail($scheduleId);
        $this->authorize('update', $schedule);

        resolve(PauseSchedule::class)($schedule);

        session()->flash('status', __('Schedule paused. Its upcoming obligations were withdrawn.'));
    }

    public function resume(int $scheduleId): void
    {
        $schedule = Schedule::query()->findOrFail($scheduleId);
        $this->authorize('update', $schedule);

        resolve(ResumeSchedule::class)($schedule);

        session()->flash('status', __('Schedule resumed.'));
    }

    public function render(): View
    {
        $this->authorize('viewAny', Schedule::class);

        return view('scheduling::schedules.index', [
            'schedules' => $this->schedules(),
            'canManage' => auth()->user()?->can('create', Schedule::class) ?? false,
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Schedule>
     */
    private function schedules(): LengthAwarePaginator
    {
        return Schedule::query()
            ->with(['template:id,name,code', 'zone:id,name'])
            ->withCount('sites')
            ->when($this->onlyActive, fn ($query) => $query->where('active', true))
            ->when($this->search !== '', function ($query): void {
                $termino = '%'.$this->search.'%';

                $query->where(function ($q) use ($termino): void {
                    $q->where('name', 'ilike', $termino)
                        ->orWhereHas('template', fn ($t) => $t->where('name', 'ilike', $termino));
                });
            })
            ->orderBy('name')
            ->paginate(20);
    }
}
