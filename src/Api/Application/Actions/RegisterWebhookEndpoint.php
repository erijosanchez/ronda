<?php

declare(strict_types=1);

namespace Ronda\Api\Application\Actions;

use Illuminate\Support\Str;
use Ronda\Api\Domain\Exceptions\UnsafeWebhookUrl;
use Ronda\Api\Domain\Models\WebhookEndpoint;
use Ronda\Api\Domain\Webhooks\WebhookEvent;
use Ronda\Api\Domain\Webhooks\WebhookUrl;

/**
 * Da de alta un destino de avisos. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * La direccion pasa por WebhookUrl, que es quien decide si es segura. Va aqui y
 * no en la pantalla porque manana esto se configura tambien por API, y una
 * comprobacion que vive en un formulario protege a un formulario, no al
 * sistema.
 *
 * El secreto lo genera Ronda y se muestra al cliente para que lo ponga en su
 * lado: dejar que lo elija acaba en secretos como «1234».
 *
 * @throws UnsafeWebhookUrl
 */
final readonly class RegisterWebhookEndpoint
{
    /**
     * @param  list<WebhookEvent>  $events
     */
    public function __invoke(string $url, array $events, ?string $description = null): WebhookEndpoint
    {
        $direccion = WebhookUrl::fromString($url, (bool) config('webhooks.allow_insecure', false));

        return WebhookEndpoint::query()->create([
            'url' => $direccion->value,
            'description' => $description,
            'secret' => Str::random(48),
            'subscribed_events' => array_values(array_unique(array_map(
                static fn (WebhookEvent $evento): string => $evento->value,
                $events,
            ))),
            'is_active' => true,
        ]);
    }
}
