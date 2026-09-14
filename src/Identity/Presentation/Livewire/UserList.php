<?php

declare(strict_types=1);

namespace Ronda\Identity\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Ronda\Identity\Application\Actions\DeleteUser;
use Ronda\Identity\Domain\Exceptions\CannotDeleteLastOwner;
use Ronda\Identity\Domain\Exceptions\CannotDeleteSelf;
use Ronda\Identity\Domain\Models\User;

/**
 * Listado de personas del cliente. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Pagina siempre (regla 5).
 */
final class UserList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * La baja se delega en la Action, que es quien sostiene los invariantes.
     * Aqui solo se autoriza, se invoca y se traduce el fallo a un mensaje.
     */
    public function delete(int $userId): void
    {
        $target = User::query()->findOrFail($userId);

        $this->authorize('delete', $target);

        /** @var User $actor */
        $actor = auth()->user();

        try {
            resolve(DeleteUser::class)($actor, $target);
        } catch (CannotDeleteSelf) {
            $this->addError('delete', __('You cannot delete your own account.'));

            return;
        } catch (CannotDeleteLastOwner) {
            $this->addError('delete', __('This is the last owner. Appoint another one before removing this account.'));

            return;
        }

        session()->flash('status', __('User removed.'));
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorize('viewAny', User::class);

        return view('identity::users.index', [
            'users' => $this->users(),
            'canManage' => auth()->user()?->can('create', User::class) ?? false,
            'currentId' => auth()->id(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    private function users(): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->when($this->search !== '', function ($query): void {
                $termino = '%'.$this->search.'%';

                // No se busca por telefono: va cifrado en la base y no hay
                // forma de compararlo en SQL (sec. 8.6).
                $query->where(function ($q) use ($termino): void {
                    $q->where('name', 'ilike', $termino)
                        ->orWhere('email', 'ilike', $termino);
                });
            })
            ->orderBy('name')
            ->paginate(20);
    }
}
