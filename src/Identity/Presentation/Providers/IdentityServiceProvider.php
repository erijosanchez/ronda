<?php

declare(strict_types=1);

namespace Ronda\Identity\Presentation\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Ronda\Identity\Presentation\Livewire\UserForm;
use Ronda\Identity\Presentation\Livewire\UserList;

/**
 * Registra lo que el modulo Identity aporta al contenedor.
 *
 * Separado de FortifyServiceProvider a proposito: aquel configura el paquete de
 * autenticacion y este registra las pantallas del modulo. Mezclarlos obliga a
 * leer sesenta lineas de politica de contrasenas para encontrar un componente.
 */
final class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'identity');

        Livewire::component('identity.user-list', UserList::class);
        Livewire::component('identity.user-form', UserForm::class);
    }
}
