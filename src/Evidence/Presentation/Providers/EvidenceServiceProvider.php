<?php

declare(strict_types=1);

namespace Ronda\Evidence\Presentation\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Registra lo que el modulo Evidence aporta al contenedor.
 *
 * Las rutas NO se cargan aqui: van en routes/tenant.php, dentro del grupo que
 * identifica el tenant por dominio y exige sesion.
 */
final class EvidenceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'evidence');
    }
}
