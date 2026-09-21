{{--
    Layout de la aplicacion autenticada.

    Solo se sirve dentro del dominio de un tenant, asi que da por hecho que
    `tenant()` y `auth()->user()` existen.

    Flux monta la rejilla el solo: su CSS aplica `display: grid` a cualquier
    elemento que contenga directamente un `flux:main`, y si la barra lateral va
    justo antes de la cabecera, ocupa toda la altura. Por eso el `<body>` no
    lleva clases de maquetacion.

    Todo script en linea lleva el nonce de la peticion: la CSP es
    `script-src 'self' 'nonce-<X>' 'unsafe-eval'`, sin `unsafe-inline`.
    Livewire lo toma solo de `Vite::useCspNonce()`, que ya fija el middleware
    SecurityHeaders; a Flux hay que pasarselo a mano. Ver ADR 0011.
--}}
@php
    $nonce = request()->attributes->get('csp_nonce');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title.' · '.config('app.name') : config('app.name') }}</title>

    {{-- PWA instalable (ADR 0010). El manifiesto y el service worker son
         archivos estáticos de `public/`: el service worker tiene que servirse
         desde la raíz para poder controlar toda la aplicación. --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="theme-color" content="#18181b">
    <meta name="mobile-web-app-capable" content="yes">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance(['nonce' => $nonce])
</head>
<body class="min-h-full bg-white text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">

    <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" inset="left" />

        <flux:brand :href="route('panel')" :name="config('app.name')" class="px-2" />

        <flux:navlist variant="outline">
            <flux:navlist.item icon="home" :href="route('panel')" :current="request()->routeIs('panel')">
                {{ __('Dashboard') }}
            </flux:navlist.item>

            <flux:navlist.item icon="clipboard-document-check" :href="route('submissions.pending')" :current="request()->routeIs('submissions.*')">
                {{ __('Pending today') }}
            </flux:navlist.item>

            {{-- Solo para quien revisa: a un encargado le daria un 403. --}}
            @can('viewInbox', \Ronda\Submissions\Domain\Models\Submission::class)
                <flux:navlist.item icon="inbox-stack" :href="route('reviews.index')" :current="request()->routeIs('reviews.*')">
                    {{ __('Review') }}
                </flux:navlist.item>
            @endcan

            <flux:navlist.group :heading="__('Structure')" expandable :expanded="request()->routeIs('sites.*', 'zones.*', 'positions.*')">
                <flux:navlist.item icon="building-office" :href="route('sites.index')" :current="request()->routeIs('sites.*')">
                    {{ __('Sites') }}
                </flux:navlist.item>

                <flux:navlist.item icon="map" :href="route('zones.index')" :current="request()->routeIs('zones.*')">
                    {{ __('Zones') }}
                </flux:navlist.item>

                <flux:navlist.item icon="identification" :href="route('positions.index')" :current="request()->routeIs('positions.*')">
                    {{ __('Positions') }}
                </flux:navlist.item>
            </flux:navlist.group>

            <flux:navlist.item icon="document-text" :href="route('templates.index')" :current="request()->routeIs('templates.*')">
                {{ __('Templates') }}
            </flux:navlist.item>

            <flux:navlist.item icon="calendar-days" :href="route('schedules.index')" :current="request()->routeIs('schedules.*')">
                {{ __('Schedules') }}
            </flux:navlist.item>

            <flux:navlist.item icon="users" :href="route('users.index')" :current="request()->routeIs('users.*')">
                {{ __('Users') }}
            </flux:navlist.item>

            {{-- El plan solo lo ve quien administra la cuenta: a un encargado
                 de local no le dice nada y no puede hacer nada con ello. --}}
            @can('view-plan')
                <flux:navlist.item icon="credit-card" :href="route('plan')" :current="request()->routeIs('plan')">
                    {{ __('Plan') }}
                </flux:navlist.item>
            @endcan
        </flux:navlist>

        <flux:spacer />

        {{-- El nombre del cliente, para que nadie dude en que tenant esta
             trabajando. Es informativo: no navega a ningun sitio. --}}
        <flux:text class="px-2 text-xs">
            {{ tenant('name') }}
        </flux:text>
    </flux:sidebar>

    <flux:header class="border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <livewire:notifications.notification-bell />

        <x-app.user-menu />
    </flux:header>

    <flux:main>
        {{-- Si hay alguien de Ronda dentro de esta cuenta, se ve siempre
             (sec. 15.4). No se pinta nada si no la hay.

             Va DENTRO de `flux:main` y no suelto en el `body` porque el CSS de
             Flux convierte en rejilla al elemento que contiene directamente un
             `flux:main`: un hermano suelto se colocaria como celda y rompería
             la maquetacion. Aqui queda fijo arriba del contenido igual. --}}
        <x-impersonation-banner />

        {{-- Sin señal: se avisa antes de que alguien intente entregar y se
             quede mirando una rueda girando. --}}
        <div x-data="connectionStatus" x-cloak>
            <div x-show="offline" class="mb-4">
                <flux:callout variant="warning" icon="signal-slash">
                    <flux:callout.heading>{{ __('No connection') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('What you write is saved on this device. To submit you need signal.') }}
                    </flux:callout.text>
                </flux:callout>
            </div>
        </div>

        {{ $slot }}
    </flux:main>

    @fluxScripts(['nonce' => $nonce])
</body>
</html>
