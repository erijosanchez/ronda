<?php

declare(strict_types=1);

namespace Ronda\Forms\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Forms\Domain\Models\TemplateVersion;
use Ronda\Forms\Domain\TemplateStatus;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Identity\Domain\Models\User;

/**
 * Publica una version nueva del formulario. Ver docs/adr/0012.
 *
 * Es la pieza central del versionado: **las versiones anteriores no se tocan**.
 * Un envio de marzo apunta a la version de marzo y se sigue leyendo con ella
 * aunque la plantilla haya cambiado diez veces desde entonces.
 *
 * Escribe en `template_versions` y en `templates` (el puntero a la version
 * vigente y el estado), asi que va en transaccion (regla 3): una plantilla
 * marcada como publicada cuyo puntero no se actualizo apunta a la version
 * anterior sin que nadie lo note.
 *
 * El esquema llega como FormSchema y no como array: asi es imposible publicar
 * una definicion que no haya pasado por la validacion del dominio, que es la
 * unica que hay (PostgreSQL no comprueba el contenido de un JSONB).
 */
final readonly class PublishTemplateVersion
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function __invoke(Template $template, FormSchema $schema, User $publisher): TemplateVersion
    {
        return $this->connection->transaction(function () use ($template, $schema, $publisher): TemplateVersion {
            $version = $template->versions()->create([
                'number' => $template->nextVersionNumber(),
                'schema' => $schema->toArray(),
                'published_at' => now(),
                'published_by' => $publisher->getKey(),
            ]);

            $template->forceFill([
                'current_version_id' => $version->getKey(),
                // Una plantilla archivada que vuelve a publicarse vuelve a
                // estar disponible para programar.
                'status' => TemplateStatus::Published->value,
            ])->save();

            return $version;
        });
    }
}
