<?php

declare(strict_types=1);

namespace Ronda\Platform\Infrastructure\Billing;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as Http;
use Ronda\Platform\Domain\Contracts\BillingGateway;
use Ronda\Platform\Domain\Exceptions\BillingNotConfigured;

/**
 * Elige la pasarela segun la configuracion. RONDA-PLAN-MAESTRO.md sec. 15.2
 *
 * Es el unico sitio del proyecto que sabe que existe Culqi. Poner las llaves y
 * cambiar `BILLING_GATEWAY` enciende el cobro; nada mas cambia.
 *
 * Si se elige una pasarela sin sus llaves, falla AQUI, al construirla, y no en
 * el primer cobro: enterarse de que falta una llave cuando un cliente esta
 * pagando es la peor forma de enterarse.
 */
final readonly class BillingGatewayFactory
{
    public function __construct(
        private Config $config,
        private Http $http,
    ) {}

    public function make(): BillingGateway
    {
        $nombre = (string) $this->config->get('billing.gateway', 'manual');

        return match ($nombre) {
            'manual' => new ManualBillingGateway,
            'culqi' => $this->culqi(),
            default => throw BillingNotConfigured::unknownGateway($nombre),
        };
    }

    private function culqi(): CulqiGateway
    {
        $secreta = $this->config->get('billing.culqi.secret_key');

        if (! is_string($secreta) || trim($secreta) === '') {
            throw BillingNotConfigured::missingKeys('culqi');
        }

        return new CulqiGateway(
            http: $this->http,
            secretKey: $secreta,
            baseUrl: rtrim((string) $this->config->get('billing.culqi.base_url'), '/'),
            timeout: (int) $this->config->get('billing.culqi.timeout', 30),
        );
    }
}
