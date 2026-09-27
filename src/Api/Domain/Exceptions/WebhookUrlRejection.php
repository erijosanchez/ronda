<?php

declare(strict_types=1);

namespace Ronda\Api\Domain\Exceptions;

/**
 * Por que no se acepto la direccion de un webhook.
 * RONDA-PLAN-MAESTRO.md sec. 13.2
 *
 * Los tres motivos se distinguen porque los tres tienen un arreglo distinto, y
 * quien configura el webhook necesita saber cual le toca: escribir bien la
 * direccion, ponerle https, o sacarla de su red interna.
 */
enum WebhookUrlRejection: string
{
    case Malformed = 'malformed';
    case InsecureScheme = 'insecure_scheme';
    case PrivateHost = 'private_host';
}
