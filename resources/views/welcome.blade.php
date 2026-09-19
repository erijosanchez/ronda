<!DOCTYPE html>
{{--
    Portada del dominio central. RONDA-PLAN-MAESTRO.md sec. 15.3

    Lo justo para que alguien entienda que es esto y se registre. El sitio
    publico de verdad —precios, casos, ayuda— llega con el resto de la fase 2;
    esta pagina existe porque el registro ya funciona y tiene que verse.

    No hay enlace de «entrar»: aqui no se entra. La sesion se abre en la
    direccion de cada cliente (tuempresa.ronda.pe), no en el dominio central.
--}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <meta name="description" content="{{ __('Operational control for companies with branches: what has to be done, who did it, and the proof.') }}">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="theme-color" content="#18181b">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <div class="mx-auto flex min-h-full max-w-3xl flex-col px-6 py-10">
        <header class="flex items-center justify-between">
            <span class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</span>

            <a href="{{ route('register.tenant') }}"
               class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                {{ __('Create your account') }}
            </a>
        </header>

        <main class="flex-1 py-16">
            <h1 class="max-w-2xl text-3xl font-semibold tracking-tight sm:text-4xl">
                {{ __('Know what happened in every branch, today.') }}
            </h1>

            <p class="mt-4 max-w-xl text-lg text-zinc-600 dark:text-zinc-400">
                {{ __('Ronda turns the checks each branch owes into a list with a deadline, collects them with photo and location, and tells you which ones are missing before you have to ask.') }}
            </p>

            <ul class="mt-10 grid gap-6 sm:grid-cols-3">
                <li>
                    <h2 class="font-medium">{{ __('Scheduled, not remembered') }}</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Daily, weekly or monthly rounds. Each one opens and closes at its hour, in the time zone of its branch.') }}
                    </p>
                </li>
                <li>
                    <h2 class="font-medium">{{ __('Evidence that holds up') }}</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Photos with date and location, stored privately. Nobody sees what their role does not allow.') }}
                    </p>
                </li>
                <li>
                    <h2 class="font-medium">{{ __('Works without signal') }}</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('The phone keeps the report and sends it when there is signal again. Nothing gets lost and nothing is sent twice.') }}
                    </p>
                </li>
            </ul>

            <div class="mt-12 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Already have an account? Sign in at your own address, the one that ends in :domain.', ['domain' => config('platform.domain')]) }}
                </p>
            </div>
        </main>

        <footer class="border-t border-zinc-200 py-6 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
            {{ config('app.name') }} &middot; {{ date('Y') }}
        </footer>
    </div>
</body>
</html>
