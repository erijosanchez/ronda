<?php

declare(strict_types=1);

namespace Ronda\Platform\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Hasta donde llega un plan. RONDA-PLAN-MAESTRO.md sec. 3.6
 *
 * `null` es «sin limite», no «cero». La distincion es todo el objeto: el plan
 * Pro tiene plantillas ilimitadas y un plan mal cargado no puede acabar
 * pareciendose a el.
 *
 * Los limites se preguntan aqui y no con un `if` en cada Action, para que la
 * regla «Starter llega a tres plantillas» viva en un solo sitio.
 */
final readonly class PlanLimits
{
    public function __construct(
        public ?int $maxTemplates = null,
        public ?int $storageGbPerSite = null,
    ) {
        foreach (['maxTemplates' => $maxTemplates, 'storageGbPerSite' => $storageGbPerSite] as $nombre => $valor) {
            if ($valor !== null && $valor < 0) {
                throw new InvalidArgumentException("«{$nombre}» no puede ser negativo.");
            }
        }
    }

    /**
     * Sin ningun limite. Es lo que se aplica cuando un cliente todavia no tiene
     * plan: en la prueba no se le corta nada, y quien decide que pasa al
     * terminarla es la facturacion, no esta clase.
     */
    public static function unlimited(): self
    {
        return new self;
    }

    public function allowsAnotherTemplate(int $current): bool
    {
        return $this->maxTemplates === null || $current < $this->maxTemplates;
    }

    /**
     * Si cabe un archivo mas en la sede, contando lo que ya ocupa.
     *
     * Se compara en bytes y no en GB porque redondear a GB dejaria pasar
     * cientos de megas por encima del limite.
     */
    public function allowsMoreStorage(int $currentBytes, int $incomingBytes): bool
    {
        if ($this->storageGbPerSite === null) {
            return true;
        }

        return $currentBytes + $incomingBytes <= $this->storageBytesPerSite();
    }

    public function storageBytesPerSite(): ?int
    {
        return $this->storageGbPerSite === null
            ? null
            : $this->storageGbPerSite * 1024 ** 3;
    }
}
