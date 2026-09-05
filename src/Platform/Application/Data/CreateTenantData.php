<?php

declare(strict_types=1);

namespace Ronda\Platform\Application\Data;

/**
 * Datos de alta de un cliente. RONDA-PLAN-MAESTRO.md sec. 7.2
 *
 * Clase `readonly` propia y no `Spatie\LaravelData\Data`: PHP no permite que
 * una clase readonly extienda una que no lo es, asi que ningun DTO de spatie
 * puede cumplir la regla «los DTOs son inmutables» del test de arquitectura.
 * Este DTO se construye en codigo y no necesita el casteo desde peticion que
 * aporta el paquete. Ver docs/HANDOFF.md: hay que decidir cual de las dos
 * cosas manda antes de que un DTO tenga que atarse a un Request.
 *
 * La contrasena del propietario viaja en claro dentro del proceso y la hashea
 * el cast `hashed` del modelo User. En la fase 2, con el onboarding
 * self-service, se sustituye por una invitacion firmada.
 */
final readonly class CreateTenantData
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $domain,
        public string $ownerName,
        public string $ownerEmail,
        public string $ownerPassword,
    ) {}
}
