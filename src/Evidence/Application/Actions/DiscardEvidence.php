<?php

declare(strict_types=1);

namespace Ronda\Evidence\Application\Actions;

use Illuminate\Filesystem\FilesystemManager;
use Throwable;

/**
 * Borra del bucket objetos que se escribieron para un envio que no llego a
 * guardarse.
 *
 * El almacenamiento de objetos no participa en la transaccion de la base: si
 * el envio se deshace despues de subir las fotos, las fotos quedan. Esto es la
 * compensacion. Nunca se usa sobre evidencia de un envio guardado.
 *
 * No lanza: se llama mientras ya se esta propagando otro error, que es el que
 * importa. Un objeto que no se pudo borrar es basura, no un fallo de entrega.
 */
final readonly class DiscardEvidence
{
    public function __construct(
        private FilesystemManager $filesystem,
    ) {}

    /**
     * @param  list<string>  $paths
     */
    public function __invoke(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        try {
            $this->filesystem->disk(StoreEvidence::DISK)->delete($paths);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
