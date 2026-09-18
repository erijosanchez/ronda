<?php

declare(strict_types=1);

namespace Ronda\Forms\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Ronda\Forms\Application\Data\TemplateData;
use Ronda\Forms\Domain\CatalogTemplate;
use Ronda\Forms\Domain\Exceptions\InvalidFormSchema;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Identity\Domain\Models\User as Publisher;

/**
 * Instala una plantilla del catalogo en el cliente.
 * RONDA-PLAN-MAESTRO.md sec. 3.5
 *
 * Lo que deja es una plantilla NORMAL, ya publicada: desde ese momento se
 * edita, se versiona y se programa como cualquier otra, y nadie recuerda que
 * vino del catalogo. Instalar es un atajo de arranque, no un vinculo.
 *
 * Escribe plantilla y version en una transaccion (regla 3). Si ya existe una
 * plantilla con ese codigo, se devuelve tal cual: instalar dos veces no
 * duplica ni pisa lo que el cliente haya cambiado.
 */
final readonly class InstallCatalogTemplate
{
    public function __construct(
        private ConnectionInterface $connection,
        private CreateTemplate $createTemplate,
        private PublishTemplateVersion $publishVersion,
    ) {}

    /**
     * @throws InvalidFormSchema si el esquema del catalogo dejara de ser valido
     */
    public function __invoke(CatalogTemplate $catalog, Publisher $publisher): Template
    {
        $existente = Template::query()->where('code', $catalog->value)->first();

        if ($existente instanceof Template) {
            return $existente;
        }

        // Se construye ANTES de escribir nada: si el esquema del catalogo no se
        // sostiene, no queda una plantilla huerfana sin versiones.
        $schema = FormSchema::fromArray($catalog->schema());

        return $this->connection->transaction(function () use ($catalog, $schema, $publisher): Template {
            $template = ($this->createTemplate)(new TemplateData(
                code: $catalog->value,
                name: $catalog->label(),
                description: $catalog->description(),
            ));

            /** @var Publisher $publisher */
            ($this->publishVersion)($template, $schema, $publisher);

            return $template->refresh();
        });
    }
}
