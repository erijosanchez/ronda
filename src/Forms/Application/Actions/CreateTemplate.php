<?php

declare(strict_types=1);

namespace Ronda\Forms\Application\Actions;

use Ronda\Forms\Application\Data\TemplateData;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Forms\Domain\TemplateStatus;

/**
 * Crea una plantilla vacia. RONDA-PLAN-MAESTRO.md sec. 9.2
 *
 * Nace en borrador y sin ninguna version: el contenido llega con
 * PublishTemplateVersion. Escribe una sola tabla, asi que no abre transaccion
 * (la regla 3 la exige para escrituras multi-tabla).
 */
final readonly class CreateTemplate
{
    public function __invoke(TemplateData $data): Template
    {
        return Template::create([
            ...$data->toAttributes(),
            'status' => TemplateStatus::Draft->value,
        ]);
    }
}
