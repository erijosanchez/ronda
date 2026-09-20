<!DOCTYPE html>
{{--
    404. Se ve sobre todo cuando alguien escribe mal el subdominio de su
    empresa, asi que lo primero que dice es como se llega al sitio correcto.

    No enlaza a ningun cliente ni confirma cuales existen: decirle a quien
    prueba direcciones «esta no» es lo mismo que decirle «esta si».
--}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Page not found') }} · {{ config('app.name') }}</title>
    <meta name="robots" content="noindex">
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <main class="flex min-h-full flex-col items-center justify-center px-6 py-12 text-center">
        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">404</p>

        <h1 class="mt-2 text-2xl font-semibold tracking-tight">
            {{ __('This address does not exist') }}
        </h1>

        <p class="mt-3 max-w-md text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Check the address of your company. If you do not remember it, ask whoever administers your account.') }}
        </p>

        {{-- Al sitio de Ronda, no a `/` del dominio en curso: esta pagina se
             ve sobre todo en un subdominio que no existe, y ahi `/` tampoco
             lleva a ningun sitio. --}}
        <a href="{{ config('app.url') }}"
           class="mt-8 rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
            {{ __('Go to :app', ['app' => config('app.name')]) }}
        </a>
    </main>
</body>
</html>
