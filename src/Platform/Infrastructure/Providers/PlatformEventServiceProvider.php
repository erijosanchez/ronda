<?php

declare(strict_types=1);

namespace Ronda\Platform\Infrastructure\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Ronda\Platform\Domain\Events\TenantProvisioned;
use Ronda\Platform\Infrastructure\Listeners\MarkTenantProvisioned;

/**
 * Enlaza los oyentes de la plataforma.
 *
 * Vive en `Infrastructure` por la misma razon que el de Notifications: lo que
 * registra son oyentes de infraestructura, y `Presentation` no puede depender
 * de `Infrastructure` (deptrac lo verifica). El despachador se inyecta en vez
 * de usar la facade `Event` (CLAUDE.md, regla 8).
 */
final class PlatformEventServiceProvider extends ServiceProvider
{
    public function boot(Dispatcher $events): void
    {
        // Quien provisiona no tiene por que saber que alguien esta esperando
        // en una pantalla: solo anuncia que termino.
        $events->listen(TenantProvisioned::class, MarkTenantProvisioned::class);
    }
}
