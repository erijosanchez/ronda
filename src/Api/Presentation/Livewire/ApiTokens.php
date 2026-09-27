<?php

declare(strict_types=1);

namespace Ronda\Api\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Ronda\Api\Application\Actions\IssueApiToken;
use Ronda\Api\Domain\ApiScope;
use Ronda\Identity\Domain\Models\User;
use Ronda\Platform\Domain\Contracts\PlanProvider;
use Ronda\Platform\Domain\PlanFeature;

/**
 * Tokens de la API, desde el panel del cliente.
 * RONDA-PLAN-MAESTRO.md sec. 13.1
 *
 * Valida, invoca la Action y devuelve (regla 1). Pagina (regla 5).
 *
 * El token se muestra UNA vez, justo despues de crearlo: en la base solo queda
 * su hash. Decirlo en la pantalla evita la llamada de soporte de quien cerro la
 * ventana pensando que podria volver a verlo.
 */
final class ApiTokens extends Component
{
    use WithPagination;

    public string $name = '';

    /** @var list<string> */
    public array $scopes = [];

    /** El token recien creado, en claro. Se pierde al recargar. */
    public string $justCreated = '';

    public function mount(): void
    {
        $this->authorize('manage-api');
    }

    public function create(IssueApiToken $issue): void
    {
        $this->authorize('manage-api');

        $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:60'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['required', 'string'],
        ]);

        /** @var User $duena */
        $duena = auth()->user();

        $alcances = array_values(array_filter(array_map(
            ApiScope::tryFrom(...),
            $this->scopes,
        )));

        $this->justCreated = $issue($duena, $this->name, $alcances)['token'];

        $this->reset('name', 'scopes');
        $this->resetPage();
    }

    public function revoke(int $id): void
    {
        $this->authorize('manage-api');

        /** @var User $duena */
        $duena = auth()->user();

        // Solo los propios: revocar el token de otra persona es cosa de su
        // ficha, no de esta pantalla.
        $duena->tokens()->whereKey($id)->delete();
    }

    public function render(PlanProvider $plan): View
    {
        /** @var User $duena */
        $duena = auth()->user();

        return view('api::tokens', [
            'tokens' => $duena->tokens()->latest('created_at')->paginate(10),
            'availableScopes' => ApiScope::cases(),
            // Si el plan no trae API, los tokens que existan no sirven. Vale
            // mas decirlo aqui que dejar que lo descubran con un 403.
            'inPlan' => $plan->allows(PlanFeature::Api),
        ]);
    }
}
