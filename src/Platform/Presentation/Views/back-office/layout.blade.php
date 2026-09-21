<!DOCTYPE html>
{{--
    Back-office de Ronda. RONDA-PLAN-MAESTRO.md sec. 15.4

    Layout propio y deliberadamente distinto al de los clientes: quien da
    soporte tiene que saber de un vistazo si esta en Ronda o dentro de la casa
    de alguien. La franja oscura de arriba es para eso.
--}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? __('Ronda support') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-zinc-100 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <header class="bg-zinc-900 text-white dark:bg-black">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-3">
            <a href="{{ route('back-office.tenants') }}" class="flex items-center gap-2 text-sm font-semibold">
                {{ config('app.name') }}
                <span class="rounded bg-amber-400 px-1.5 py-0.5 text-xs font-bold text-zinc-900">
                    {{ __('SUPPORT') }}
                </span>
            </a>

            <div class="flex items-center gap-4 text-sm">
                <span class="text-zinc-300">{{ auth('platform')->user()?->name }}</span>

                <form method="POST" action="{{ route('back-office.logout') }}">
                    @csrf
                    <button type="submit" class="underline underline-offset-4 hover:text-zinc-300">
                        {{ __('Log out') }}
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-6 py-8">
        @if (session('status'))
            <p class="mb-6 rounded-md bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                {{ session('status') }}
            </p>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
