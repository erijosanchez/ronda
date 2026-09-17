<?php

declare(strict_types=1);

namespace Ronda\Notifications\Presentation\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Ronda\Notifications\Presentation\Console\SlaChecksCommand;
use Ronda\Notifications\Presentation\Livewire\NotificationBell;
use Ronda\Notifications\Presentation\Livewire\NotificationList;

/**
 * Registra las pantallas del modulo Notifications.
 *
 * Los oyentes y la entrega de avisos se enchufan en
 * NotificationsEventServiceProvider, que vive en `Infrastructure`.
 */
final class NotificationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'notifications');

        Livewire::component('notifications.notification-list', NotificationList::class);
        Livewire::component('notifications.notification-bell', NotificationBell::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SlaChecksCommand::class]);
        }
    }
}
