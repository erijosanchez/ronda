<!DOCTYPE html>
{{--
    Sitio publico. RONDA-PLAN-MAESTRO.md sec. 15.3

    Sin Livewire ni Flux: aqui no hay nada que reaccione, y quien llega por
    primera vez no tiene por que descargar un framework para leer tres
    parrafos. Solo la hoja de estilos.

    No hay enlace de «entrar»: aqui no se entra. La sesion se abre en la
    direccion de cada cliente, no en el dominio central.
--}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name')) · {{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', __('Operational control for companies with branches: what has to be done, who did it, and the proof.'))">

    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="theme-color" content="#18181b">

    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <header class="border-b border-zinc-200 dark:border-zinc-800">
        <nav class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
            <a href="{{ route('home') }}" class="text-lg font-semibold tracking-tight">
                {{ config('app.name') }}
            </a>

            <div class="flex items-center gap-5 text-sm">
                <a href="{{ route('pricing') }}" class="hover:underline underline-offset-4">
                    {{ __('Pricing') }}
                </a>

                <a href="{{ route('register.tenant') }}"
                   class="rounded-md bg-zinc-900 px-4 py-2 font-medium text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                    {{ __('Create your account') }}
                </a>
            </div>
        </nav>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="mt-24 border-t border-zinc-200 dark:border-zinc-800">
        <div class="mx-auto max-w-5xl px-6 py-8 text-sm text-zinc-500 dark:text-zinc-400">
            <p>{{ config('app.name') }} · {{ date('Y') }}</p>

            <p class="mt-2">
                {{ __('Already have an account? Sign in at your own address, the one that ends in :domain.', [
                    'domain' => config('platform.domain'),
                ]) }}
            </p>
        </div>
    </footer>
</body>
</html>
