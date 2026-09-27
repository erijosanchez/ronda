<?php

declare(strict_types=1);

namespace Ronda\Api\Domain\Webhooks;

use Carbon\CarbonImmutable;
use Ronda\Api\Domain\Models\WebhookDelivery;

/**
 * Quien pone un aviso en la red. RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * El contrato vive en Domain y la implementacion con HTTP en Infrastructure
 * porque el Job de Application no puede depender de Infrastructure (deptrac lo
 * verifica, CLAUDE.md). Es la misma forma que `PlanProvider`.
 *
 * Que el aviso viaje por HTTP es un detalle: lo que el resto del sistema
 * necesita es «manda esta entrega y dime si llego».
 */
interface WebhookSender
{
    /**
     * Manda la entrega y anota como fue.
     *
     * @return bool si el otro lado la acepto
     */
    public function __invoke(WebhookDelivery $delivery, ?CarbonImmutable $now = null): bool;

    /**
     * La firma que viaja en `X-Ronda-Signature`.
     *
     * Esta en el contrato porque no es un detalle interno: es lo que el
     * cliente reproduce en su lado para comprobar que el aviso es nuestro.
     */
    public function sign(string $timestamp, string $body, string $secret): string;
}
