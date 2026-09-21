<?php

declare(strict_types=1);

namespace Ronda\Platform\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Identity\Domain\Models\User;
use Ronda\Platform\Application\Actions\StartImpersonation;
use Ronda\Platform\Domain\Exceptions\CannotImpersonate;
use Ronda\Platform\Domain\Models\ImpersonationEntry;
use Ronda\Platform\Domain\Models\PlatformUser;
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

    /** A quien se va a suplantar. */
    public string $impersonateUserId = '';

    /** Por que. Obligatorio y con minimo: lo va a leer el cliente. */
    public string $reason = '';

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    /**
     * Entra a la cuenta del cliente. RONDA-PLAN-MAESTRO.md sec. 15.4
     *
     * Valida, invoca la Action y devuelve (regla 1). El motivo, el limite de
     * tiempo, el registro y el aviso al cliente los pone StartImpersonation:
     * si vivieran aqui, la primera API que entrara por otro lado se los
     * saltaria.
     */
    public function impersonate(StartImpersonation $start): void
    {
        $actor = auth('platform')->user();

        if (! $actor instanceof PlatformUser) {
            return;
        }

        $this->validate([
            'impersonateUserId' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:'.(int) config('platform.impersonation.min_reason', 15), 'max:500'],
        ]);

        try {
            $destino = $start(
                actor: $actor,
                tenant: $this->tenant,
                userId: (int) $this->impersonateUserId,
                reason: $this->reason,
                ip: request()->ip(),
                userAgent: (string) request()->userAgent(),
            );
        } catch (CannotImpersonate $e) {
            $this->addError('reason', $e->getMessage());

            return;
        }

        // Fuera de la aplicacion: el enlace lleva al dominio del cliente.
        $this->redirect($destino, navigate: false);
    }

    public function render(): View
    {
        $suscripcion = Subscription::query()->where('tenant_id', $this->tenant->id)->first();

        return view('platform::back-office.tenant', [
            'subscription' => $suscripcion,
            'users' => $this->impersonableUsers(),
            'minReason' => (int) config('platform.impersonation.min_reason', 15),
            'minutes' => (int) config('platform.impersonation.minutes', 30),
            'history' => ImpersonationEntry::query()
                ->where('tenant_id', $this->tenant->id)
                ->with('platformUser')
                ->latest('started_at')
                ->limit(10)
                ->get(),
            'invoices' => $suscripcion instanceof Subscription
                ? $suscripcion->invoices()->orderByDesc('period_start')->limit(12)->get()
                : collect(),
            'usage' => $this->usage(),
        ]);
    }

    /**
     * Las personas del cliente a las que se puede suplantar.
     *
     * @return list<array{id: int, label: string}>
     */
    private function impersonableUsers(): array
    {
        if (! $this->tenant->isReady()) {
            return [];
        }

        try {
            return $this->tenant->run(static fn (): array => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(static fn (User $u): array => [
                    'id' => (int) $u->id,
                    'label' => $u->name.' · '.$u->email,
                ])
                ->all());
        } catch (Throwable $e) {
            report($e);

            return [];
        }
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
