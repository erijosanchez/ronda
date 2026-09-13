<?php

declare(strict_types=1);

namespace Ronda\Identity\Presentation\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider;
use Illuminate\Support\Facades\Gate;
use Ronda\Directory\Application\Policies\SitePolicy;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Identity\Application\Policies\OwnerGate;
use Ronda\Identity\Application\Policies\UserPolicy;
use Ronda\Identity\Domain\Models\User;

/**
 * Enlaza modelos con sus Policies y registra el unico Gate::before del
 * proyecto. RONDA-PLAN-MAESTRO.md sec. 10.3
 *
 * Cada modulo ira anadiendo aqui sus Policies conforme aparezcan sus modelos.
 * Un modelo sin Policy no es un descuido silencioso: Laravel deniega por
 * defecto cuando no encuentra una.
 */
final class AuthorizationServiceProvider extends AuthServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Site::class => SitePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // El propietario del tenant pasa por encima de toda Policy.
        //
        // spatie/laravel-permission registra su propio Gate::before para
        // resolver permisos por nombre; los dos conviven porque ambos
        // devuelven null cuando no les toca decidir.
        Gate::before(new OwnerGate);
    }
}
