<?php

declare(strict_types=1);

namespace Ronda\Platform\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Identity\Domain\Models\User;
use Ronda\Platform\Domain\Models\Subscription;
use Ronda\Platform\Domain\Models\Tenant;
use Ronda\Submissions\Domain\Models\Submission;
use Throwable;

/**
 * La ficha de un cliente, para soporte. RONDA-PLAN-MAESTRO.md sec. 15.4
 *
 * Aqui SI se entra a la base del cliente —una sola, la suya— para contar lo que
 * explica una llamada de soporte: cuantas sedes tiene, cuanta gente, cuantos
 * reportes lleva y cuando fue el ultimo. Sin eso, la primera pregunta de cada
 * conversacion seria «¿y ustedes que ven?».
 *
 * Se cuenta, no se lee: esta pantalla no muestra el contenido de ningun
 * reporte. Para ver lo que ve el cliente esta la suplantacion, que queda
 * registrada y avisa a su propietario.
 */
#[Layout('platform::back-office.layout')]
final class BackOfficeTenant extends Component
{
    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function render(): View
    {
        $suscripcion = Subscription::query()->where('tenant_id', $this->tenant->id)->first();

        return view('platform::back-office.tenant', [
            'subscription' => $suscripcion,
            'invoices' => $suscripcion instanceof Subscription
                ? $suscripcion->invoices()->orderByDesc('period_start')->limit(12)->get()
                : collect(),
            'usage' => $this->usage(),
        ]);
    }

    /**
     * Un vistazo a la operacion del cliente, contado dentro de su base.
     *
     * Si la base todavia no existe —un alta que fallo a medio provisionar— se
     * devuelve null en vez de reventar: esa ficha es justamente la que hay que
     * poder abrir para entender que paso.
     *
     * @return array{sites: int, users: int, templates: int, submissions: int, last: ?string}|null
     */
    private function usage(): ?array
    {
        if (! $this->tenant->isReady()) {
            return null;
        }

        try {
            return $this->tenant->run(static fn (): array => [
                'sites' => Site::query()->withoutGlobalScopes()->count(),
                'users' => User::query()->count(),
                'templates' => Template::query()->count(),
                'submissions' => Submission::query()->count(),
                'last' => Submission::query()->max('submitted_at'),
            ]);
        } catch (Throwable $e) {
            // Se anota: una ficha que no puede contar es justo la que alguien
            // va a mirar, y tragarse el motivo deja a soporte sin nada que
            // decir.
            report($e);

            return null;
        }
    }
}
