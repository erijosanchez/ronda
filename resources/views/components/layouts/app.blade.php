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

            <flux:navlist.item icon="building-office" :href="route('sites.index')" :current="request()->routeIs('sites.*')">
                {{ __('Sites') }}
            </flux:navlist.item>

            <flux:navlist.item icon="document-text" :href="route('templates.index')" :current="request()->routeIs('templates.*')">
                {{ __('Templates') }}
            </flux:navlist.item>

            <flux:navlist.item icon="users" :href="route('users.index')" :current="request()->routeIs('users.*')">
                {{ __('Users') }}
            </flux:navlist.item>
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

        <x-app.user-menu />
    </flux:header>

    <flux:main>
        {{ $slot }}
    </flux:main>

    @fluxScripts(['nonce' => $nonce])
</body>
</html>
