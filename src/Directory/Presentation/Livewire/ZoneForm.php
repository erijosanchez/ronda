<?php

declare(strict_types=1);

namespace Ronda\Directory\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Ronda\Directory\Application\Actions\CreateZone;
use Ronda\Directory\Application\Actions\UpdateZone;
use Ronda\Directory\Application\Data\ZoneData;
use Ronda\Directory\Domain\Exceptions\CannotDeleteZone;
use Ronda\Directory\Domain\Models\Zone;
use Ronda\Identity\Domain\Models\User;

/**
 * Alta y edicion de una zona. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Valida, invoca la Action y devuelve (regla 1). Que una zona no pueda colgar
 * de si misma ni de sus hijas lo decide la Action; aqui solo se muestra su
 * mensaje.
 */
final class ZoneForm extends Component
{
    public ?Zone $zone = null;

    public string $name = '';

    public string $parentId = '';

    public string $managerId = '';

    public function mount(?Zone $zone = null): void
    {
        if ($zone instanceof Zone && $zone->exists) {
            $this->authorize('update', $zone);

            $this->zone = $zone;
            $this->name = $zone->name;
            $this->parentId = $zone->parent_id === null ? '' : (string) $zone->parent_id;
            $this->managerId = $zone->manager_id === null ? '' : (string) $zone->manager_id;

            return;
        }

        $this->authorize('create', Zone::class);
    }

    public function save(): void
    {
        $this->zone instanceof Zone
            ? $this->authorize('update', $this->zone)
            : $this->authorize('create', Zone::class);

        $this->validate();

        $data = new ZoneData(
            name: trim($this->name),
            parentId: $this->parentId === '' ? null : (int) $this->parentId,
            managerId: $this->managerId === '' ? null : (int) $this->managerId,
        );

        try {
            $this->zone instanceof Zone
                ? resolve(UpdateZone::class)($this->zone, $data)
                : resolve(CreateZone::class)($data);
        } catch (CannotDeleteZone $e) {
            $this->addError('parentId', $e->getMessage());

            return;
        }

        session()->flash('status', $this->zone instanceof Zone ? __('Zone updated.') : __('Zone created.'));

        $this->redirectRoute('zones.index', navigate: true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                // Dos zonas con el mismo nombre no se distinguen en el selector
                // de la sede, que es donde se usan.
                Rule::unique('zones', 'name')->whereNull('deleted_at')->ignore($this->zone?->getKey()),
            ],
            'parentId' => ['nullable', Rule::exists('zones', 'id')->whereNull('deleted_at')],
            'managerId' => ['nullable', Rule::exists('users', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'name' => __('name'),
            'parentId' => __('parent zone'),
            'managerId' => __('manager'),
        ];
    }

    public function render(): View
    {
        return view('directory::zones.form', [
            'editing' => $this->zone instanceof Zone,
            // Una zona no puede colgar de si misma: ni se ofrece.
            'parents' => Zone::query()
                ->when($this->zone instanceof Zone, fn ($query) => $query->whereKeyNot($this->zone?->getKey()))
                ->orderBy('name')
                ->get(['id', 'name']),
            'managers' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
