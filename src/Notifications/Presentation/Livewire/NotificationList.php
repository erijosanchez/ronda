<?php

declare(strict_types=1);

namespace Ronda\Notifications\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Ronda\Identity\Domain\Models\User;

/**
 * Los avisos de quien esta mirando. RONDA-PLAN-MAESTRO.md sec. 9.3
 *
 * No hace falta Policy: una persona solo ve sus propios avisos porque la
 * consulta sale de su relacion `notifications`, no de una tabla global.
 *
 * Pagina (regla 5): un encargado acumula un aviso por entrega.
 */
final class NotificationList extends Component
{
    use WithPagination;

    #[Url(except: false)]
    public bool $onlyUnread = false;

    public function updatedOnlyUnread(): void
    {
        $this->resetPage();
    }

    public function markAsRead(string $id): void
    {
        $this->viewer()->notifications()->whereKey($id)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function markAllAsRead(): void
    {
        $this->viewer()->unreadNotifications->markAsRead();

        session()->flash('status', __('All notifications marked as read.'));
    }

    public function render(): View
    {
        return view('notifications::index', [
            'notifications' => $this->notifications(),
            'unread' => $this->viewer()->unreadNotifications()->count(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    private function notifications(): LengthAwarePaginator
    {
        return $this->viewer()
            ->notifications()
            ->when($this->onlyUnread, fn ($query) => $query->whereNull('read_at'))
            ->paginate(20);
    }

    private function viewer(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
