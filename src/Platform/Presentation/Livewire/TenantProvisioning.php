<?php

declare(strict_types=1);

namespace Ronda\Platform\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Ronda\Platform\Application\Queries\TenantSignInUrlQuery;
use Ronda\Platform\Domain\Models\Tenant;

/**
 * «Estamos preparando tu cuenta». RONDA-PLAN-MAESTRO.md sec. 15.3
 *
 * Entre el registro y poder entrar hay que crear una base de datos, migrarla,
 * sembrarla y crear al propietario. Son segundos, pero segundos en los que el
 * dominio del cliente todavia devuelve 404: mandar a alguien alli de una vez
 * seria mandarlo a un error.
 *
 * Pregunta por `provisioned_at` en la base CENTRAL, que es la unica a la que se
 * puede preguntar desde aqui.
 *
 * La direccion es publica a proposito: quien acaba de registrarse no tiene
 * sesion en ninguna parte todavia. Lo que protege la pantalla es el ULID del
 * cliente, que no se adivina, y lo unico que muestra es lo que esa persona
 * acaba de escribir.
 */
#[Layout('components.layouts.guest')]
final class TenantProvisioning extends Component
{
    public Tenant $tenant;

    /**
     * Cuantas veces se ha preguntado. Pasado un rato sin respuesta hay que
     * decirlo: una rueda girando para siempre no es informacion.
     */
    public int $checks = 0;

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function check(): void
    {
        $this->checks++;
    }

    public function render(TenantSignInUrlQuery $signInUrl): View
    {
        // Livewire vuelve a leer el modelo en cada peticion, asi que esto es
        // el estado de ahora mismo y no el del registro.
        $listo = $this->tenant->isReady();

        return view('platform::provisioning', [
            'listo' => $listo,
            'url' => $listo ? $signInUrl($this->tenant) : null,
            'tardando' => ! $listo && $this->checks >= 20,
        ]);
    }
}
