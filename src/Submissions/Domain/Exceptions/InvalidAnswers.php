<?php

declare(strict_types=1);

namespace Ronda\Submissions\Domain\Exceptions;

use DomainException;

/**
 * Las respuestas no cumplen la version del formulario.
 *
 * Lleva los errores por clave de campo, no un mensaje unico: la pantalla los
 * pinta junto a cada campo, y un «algo esta mal» en un formulario de treinta
 * campos obliga a revisarlos todos.
 */
final class InvalidAnswers extends DomainException
{
    /**
     * @param  array<string, string>  $errors  clave de campo => mensaje
     */
    public function __construct(
        public readonly array $errors,
    ) {
        parent::__construct('Las respuestas no son validas: '.implode(' ', $errors));
    }
}
