<?php

declare(strict_types=1);

namespace Ronda\Api\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Ronda\Api\Application\Actions\RegisterWebhookEndpoint;
use Ronda\Api\Application\Actions\ResendWebhookDelivery;
use Ronda\Api\Domain\Exceptions\UnsafeWebhookUrl;
use Ronda\Api\Domain\Exceptions\WebhookUrlRejection;
use Ronda\Api\Domain\Models\WebhookDelivery;
use Ronda\Api\Domain\Models\WebhookEndpoint;
use Ronda\Api\Domain\Webhooks\WebhookEvent;
use Ronda\Platform\Domain\Contracts\PlanProvider;
use Ronda\Platform\Domain\PlanFeature;

/**
 * Webhooks del cliente. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Valida, invoca la Action y devuelve (regla 1). Pagina (regla 5).
 *
 * Muestra el registro de entregas, que es lo que convierte «no me llego» en una
 * conversacion con datos, y deja reenviar a mano lo que fallo.
 */
final class Webhooks extends Component
{
    use WithPagination;

    public string $url = '';

    public string $description = '';

    /** @var list<string> */
    public array $events = [];

    /** El secreto del destino recien creado. Se ensena una vez. */
    public string $justCreatedSecret = '';

    public function mount(): void
    {
        $this->authorize('manage-api');
    }

    public function create(RegisterWebhookEndpoint $register): void
    {
        $this->authorize('manage-api');

        $this->validate([
            'url' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:120'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string'],
        ]);

        $eventos = array_values(array_filter(array_map(
            WebhookEvent::tryFrom(...),
            $this->events,
        )));

        try {
            $destino = $register(
                $this->url,
                $eventos,
                $this->description === '' ? null : $this->description,
            );
        } catch (UnsafeWebhookUrl $e) {
            // El motivo se traduce aqui: quien configura esto necesita saber si
            // le falta https o si apunto a su propia red, y en su idioma.
            $this->addError('url', $this->reject($e));

            return;
        }

        $this->justCreatedSecret = $destino->secret;
        $this->reset('url', 'description', 'events');
    }

    public function toggle(int $id): void
    {
        $this->authorize('manage-api');

        $destino = WebhookEndpoint::query()->find($id);

        if ($destino instanceof WebhookEndpoint) {
            $destino->forceFill([
                'is_active' => ! $destino->is_active,
                // Volver a encenderlo perdona los fallos anteriores: si no, un
                // destino que se apago solo no podria revivir.
                'consecutive_failures' => 0,
            ])->save();
        }
    }

    public function delete(int $id): void
    {
        $this->authorize('manage-api');

        WebhookEndpoint::query()->whereKey($id)->delete();
    }

    public function resend(int $id, ResendWebhookDelivery $resend): void
    {
        $this->authorize('manage-api');

        $entrega = WebhookDelivery::query()->find($id);

        if ($entrega instanceof WebhookDelivery) {
            $resend($entrega);
            session()->flash('status', __('Delivery queued again.'));
        }
    }

    public function render(PlanProvider $plan): View
    {
        return view('api::webhooks', [
            // Los dos listados paginan (regla 5) y cada uno lleva su propio
            // nombre de pagina: si compartieran `page`, pasar a la segunda
            // pagina del registro movería tambien la lista de destinos.
            'endpoints' => WebhookEndpoint::query()
                ->latest('created_at')
                ->paginate(10, pageName: 'destinos'),
            'deliveries' => WebhookDelivery::query()
                ->with('endpoint')
                ->latest('created_at')
                ->paginate(15, pageName: 'entregas'),
            'availableEvents' => WebhookEvent::cases(),
            // Si el plan no trae API, los destinos que existan no reciben
            // nada. Vale mas decirlo aqui que dejar que lo descubran mirando
            // un registro de entregas vacio.
            'inPlan' => $plan->allows(PlanFeature::Api),
        ]);
    }

    private function reject(UnsafeWebhookUrl $e): string
    {
        return match ($e->reason) {
            WebhookUrlRejection::Malformed => __('«:url» is not a valid address.', ['url' => $e->value]),
            WebhookUrlRejection::InsecureScheme => __('Webhooks go over https. «:scheme» is not accepted.', ['scheme' => $e->value]),
            WebhookUrlRejection::PrivateHost => __('«:host» points inside a private network. Use an address reachable from the internet.', ['host' => $e->value]),
        };
    }
}
