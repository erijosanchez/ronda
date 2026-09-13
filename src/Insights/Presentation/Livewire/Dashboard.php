<?php

declare(strict_types=1);

namespace Ronda\Insights\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Ronda\Identity\Domain\RoleName;

/**
 * Pantalla de aterrizaje tras iniciar sesion.
 * RONDA-PLAN-MAESTRO.md sec. 9.6
 *
 * De momento solo confirma quien eres, en que cliente estas y con que roles.
 * Los KPI llegan con los modulos que producen los datos: sin envios ni sedes
 * no hay nada que medir, y un tablero con cifras inventadas es peor que uno
 * vacio.
 */
final class Dashboard extends Component
{
    public function render(): View
    {
        $user = auth()->user();

        return view('insights::dashboard', [
            'userName' => (string) $user?->name,
            'tenantName' => (string) tenant('name'),
            'roles' => $this->roleLabels(),
        ]);
    }

    /**
     * Nombres legibles de los roles del usuario.
     *
     * Leer los roles para mostrarlos no es comprobar permisos: la autorizacion
     * sigue viviendo solo en las Policies.
     *
     * @return list<string>
     */
    private function roleLabels(): array
    {
        $names = auth()->user()?->roles->pluck('name')->all() ?? [];

        return array_values(array_map(
            static fn (string $name): string => RoleName::tryFrom($name)?->label() ?? $name,
            $names,
        ));
    }
}
