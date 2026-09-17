<?php

declare(strict_types=1);

namespace Ronda\Notifications\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;
use Ronda\Identity\Domain\Models\User;

/**
 * La campana de la cabecera: cuantos avisos sin leer y los ultimos.
 *
 * Se refresca sola cada minuto (`wire:poll` en la vista). Sin websockets
 * todavia: un minuto de retraso en un aviso operativo no cambia nada, y Reverb
 * se conecta cuando haya algo que merezca tiempo real.
 */
final class NotificationBell extends Component
{
    /** Cuantos avisos se asoman en el desplegable. */
    private const int PREVIEW = 5;

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        /** @var Collection<int, object> $ultimos */
        $ultimos = $user->unreadNotifications()->limit(self::PREVIEW)->get();

        return view('notifications::bell', [
            'unread' => $user->unreadNotifications()->count(),
            'latest' => $ultimos,
        ]);
    }
}
