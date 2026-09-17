<?php

declare(strict_types=1);

namespace Ronda\Evidence\Application\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Routing\UrlGenerator;
use Ronda\Evidence\Domain\Models\Attachment;
use Ronda\Identity\Domain\Models\User;

/**
 * La URL firmada de un archivo de evidencia. ADR 0009.
 *
 * La Policy se evalua ANTES de firmar: una URL firmada es un permiso que viaja
 * solo, y no se emite a quien no lo tiene.
 *
 * La URL apunta a la aplicacion, no al bucket. Asi la firma lleva el dominio del
 * tenant (en otro cliente no valida), la Policy se vuelve a comprobar al
 * servirla, y el bucket nunca necesita ser alcanzable desde fuera.
 */
final readonly class SignedEvidenceUrlQuery
{
    public function __construct(
        private UrlGenerator $url,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(Attachment $attachment, User $viewer): string
    {
        if (! $viewer->can('view', $attachment)) {
            throw new AuthorizationException;
        }

        $minutos = (int) config('security.evidence.signed_url_ttl', 5);

        return $this->url->temporarySignedRoute(
            'evidence.show',
            CarbonImmutable::now()->addMinutes($minutos),
            ['attachment' => $attachment->getKey()],
        );
    }
}
