<?php

declare(strict_types=1);

namespace Ronda\Scheduling\Domain;

/**
 * Las formas de repeticion que ofrece la pantalla de programaciones.
 *
 * No son las frecuencias de la RFC 5545: son lo que un administrador reconoce.
 * `Custom` deja escribir la RRULE entera para los casos que no caben en las
 * otras tres («el primer lunes de cada mes»).
 */
enum RecurrenceFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Custom = 'custom';

    public function label(): string
    {
        return __('recurrence-frequency.'.$this->value);
    }
}
