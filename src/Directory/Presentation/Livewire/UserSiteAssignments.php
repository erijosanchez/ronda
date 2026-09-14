<?php

declare(strict_types=1);

namespace Ronda\Directory\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Ronda\Directory\Application\Actions\AssignUserToSites;
use Ronda\Directory\Application\Data\SiteAssignmentData;
use Ronda\Directory\Domain\Models\Position;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\RoleName;

/**
 * En que sedes trabaja una persona. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Esta pantalla es la que da sentido a la frontera por sede: hasta que alguien
 * asigna sedes, un encargado no ve ninguna.
 *
 * El listado pagina (regla 5), pero lo marcado NO vive en la pagina: vive en
 * `$assignments`, indexado por sede. Asi se puede recorrer un parque de
 * trescientas sedes y guardar una sola vez al final, sin perder lo elegido al
 * cambiar de pagina.
 */
final class UserSiteAssignments extends Component
{
    use WithPagination;

    public User $user;

    /**
     * Estado completo de la asignacion, no solo el de la pagina visible.
     * Indexado por id de sede.
     *
     * @var array<int, array{position_id: string|null, role: string|null}>
     */
    public array $assignments = [];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function mount(User $user): void
    {
        $this->authorize('assignSites', $user);

        $this->user = $user;

        foreach ($user->sites as $site) {
            $this->assignments[$site->getKey()] = [
                'position_id' => $site->pivot?->position_id === null
                    ? null
                    : (string) $site->pivot->position_id,
                'role' => $site->pivot?->role,
            ];
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggle(int $siteId): void
    {
        if (array_key_exists($siteId, $this->assignments)) {
            unset($this->assignments[$siteId]);

            return;
        }

        $this->assignments[$siteId] = ['position_id' => null, 'role' => null];
    }

    public function save(): void
    {
        $this->authorize('assignSites', $this->user);

        $this->validate();

        $data = [];

        foreach ($this->assignments as $siteId => $detalle) {
            $data[] = new SiteAssignmentData(
                siteId: (int) $siteId,
                positionId: $this->toId($detalle['position_id'] ?? null),
                role: RoleName::tryFrom((string) ($detalle['role'] ?? '')),
            );
        }

        resolve(AssignUserToSites::class)($this->user, $data);

        session()->flash('status', __('Assignments saved.'));
        $this->redirectRoute('users.index', navigate: true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'assignments' => ['array'],
            // Se valida lo que llega del navegador aunque las casillas salgan
            // de la propia consulta: el estado de un componente Livewire viaja
            // por el cliente y se puede manipular.
            'assignments.*.position_id' => ['nullable', Rule::exists('positions', 'id')->whereNull('deleted_at')],
            'assignments.*.role' => ['nullable', Rule::enum(RoleName::class)],
        ];
    }

    public function render(): View
    {
        return view('directory::sites.assignments', [
            'sites' => $this->sites(),
            'positions' => Position::query()->orderByDesc('level')->get(['id', 'name']),
            'roles' => RoleName::cases(),
        ]);
    }

    /**
     * Las sedes que el propio asignador alcanza: AssignedSitesScope filtra la
     * consulta. No se puede dar acceso a una sede que uno mismo no ve.
     *
     * @return LengthAwarePaginator<int, Site>
     */
    private function sites(): LengthAwarePaginator
    {
        return Site::query()
            ->when($this->search !== '', function ($query): void {
                $termino = '%'.$this->search.'%';

                $query->where(function ($q) use ($termino): void {
                    $q->where('name', 'ilike', $termino)
                        ->orWhere('code', 'ilike', $termino);
                });
            })
            ->orderBy('name')
            ->paginate(15);
    }

    private function toId(?string $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
