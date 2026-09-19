<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\Contracts;

use Ronda\Platform\Domain\Billing\ChargeRequest;
use Ronda\Platform\Domain\Billing\ChargeResult;
use Ronda\Platform\Domain\Exceptions\BillingGatewayFailed;

/**
 * Por donde sale el dinero. RONDA-PLAN-MAESTRO.md sec. 15.2
 *
 * El contrato es deliberadamente PEQUENO —guardar una tarjeta y cobrar un
 * importe— y no usa los objetos «plan» ni «suscripcion» de ninguna pasarela.
 *
 * La razon es el modelo de precio: Ronda cobra por SEDE ACTIVA (sec. 15.1), asi
 * que el importe cambia de un mes a otro. Una suscripcion de monto fijo en la
 * pasarela cobraria lo de la foto del dia que se creo, y habria que corregirla
 * cada vez que el cliente abre o cierra una sede. Quien sabe cuanto toca cobrar
 * este mes es Ronda; la pasarela solo ejecuta el cobro.
 *
 * Eso ademas hace intercambiable a la pasarela: Culqi hoy, Izipay o Stripe
 * manana, sin tocar ni una Action.
 */
interface BillingGateway
{
    /**
     * Como se llama, para dejarlo escrito en la factura: saber con que pasarela
     * se cobro cada cosa es lo que permite conciliar despues de un cambio.
     */
    public function name(): string;

    /**
     * Guarda la tarjeta y devuelve con que referirse a ella despues.
     *
     * Recibe un token de un solo uso creado EN EL NAVEGADOR: los datos de la
     * tarjeta no pasan nunca por nuestro servidor (sec. 10.4).
     *
     * @throws BillingGatewayFailed
     */
    public function storeCard(string $token, string $email, string $reference): string;

    /**
     * Cobra. Un rechazo vuelve dentro del resultado, no como excepcion.
     *
     * @throws BillingGatewayFailed si la pasarela no se pudo consultar
     */
    public function charge(ChargeRequest $request): ChargeResult;
}
