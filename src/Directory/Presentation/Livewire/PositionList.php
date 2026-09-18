<?php

declare(strict_types=1);

namespace Ronda\Directory\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Ronda\Directory\Application\Actions\CreatePosition;
use Ronda\Directory\Application\Actions\DeletePosition;
use Ronda\Directory\Application\Actions\UpdatePosition;
use Ronda\Directory\Application\Data\PositionData;
use Ronda\Directory\Domain\Exceptions\CannotDeletePosition;
use Ronda\Directory\Domain\Models\Position;

/**
 * Los cargos del organigrama, en una sola pantalla.
 * RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Un cargo son dos campos (nombre y nivel) y se crean de cinco en cinco al
 * montar el cliente: mandarlos a una pantalla aparte por cada alta seria pedir
 * tres clics para escribir una palabra. La lista y el alta comparten sitio.
 *
 * Valida, invoca la Action y devuelve (regla 1); pagina (regla 5).
 */
final class PositionList extends Component
{
    use WithPagination;

    public string $name = '';

    public string $level = '0';

    /** Cargo que se esta editando, o null si se esta creando. */
    public ?int $editingId = null;

    public function edit(int $positionId): void
    {
        $position = Position::query()->findOrFail($positionId);
        $this->authorize('update', $position);

        $this->editingId = $position->id;
        $this->name = $position->name;
        $this->level = (string) $position->level;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset(['name', 'level', 'editingId']);
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $position = $this->editingId === null ? null : Position::query()->findOrFail($this->editingId);

        $position instanceof Position
            ? $this->authorize('update', $position)
            : $this->authorize('create', Position::class);

        $this->validate();

        $data = new PositionData(name: trim($this->name), level: (int) $this->level);

        $position instanceof Position
            ? resolve(UpdatePosition::class)($position, $data)
            : resolve(CreatePosition::class)($data);

        session()->flash('status', $position instanceof Position ? __('Position updated.') : __('Position created.'));

        $this->cancel();
    }

    public function delete(int $positionId): void
    {
        $position = Position::query()->findOrFail($positionId);
        $this->authorize('delete', $position);

        try {
            resolve(DeletePosition::class)($position);
        } catch (CannotDeletePosition $e) {
            $this->addError('position', $e->getMessage());

            return;
        }

        session()->flash('status', __('Position deleted.'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('positions', 'name')->whereNull('deleted_at')->ignore($this->editingId),
            ],
            // El nivel ordena el organigrama: 0 es la base.
            'level' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return ['name' => __('name'), 'level' => __('level')];
    }

    public function render(): View
    {
        $this->authorize('viewAny', Position::class);

        return view('directory::positions.index', [
            'positions' => $this->positions(),
            'canManage' => auth()->user()?->can('create', Position::class) ?? false,
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Position>
     */
    private function positions(): LengthAwarePaginator
    {
        return Position::query()
            ->withCount('assignments')
            ->orderByDesc('level')
            ->orderBy('name')
            ->paginate(20);
    }
}
