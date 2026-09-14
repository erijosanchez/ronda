<?php

declare(strict_types=1);

namespace Ronda\Forms\Application\Data;

/**
 * Identidad de una plantilla, ya validada. Ver docs/adr/0012.
 *
 * El contenido del formulario NO viaja aqui: vive en sus versiones, y se
 * publica con PublishTemplateVersion.
 */
final readonly class TemplateData
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $description = null,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toAttributes(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
