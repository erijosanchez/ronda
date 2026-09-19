<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>

    {{-- Tambien aqui: el encargado instala la aplicacion desde el login, que
         es la primera pantalla que ve (ADR 0010). --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="theme-color" content="#18181b">
    <meta name="mobile-web-app-capable" content="yes">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <main class="flex min-h-full flex-col justify-center px-6 py-12">
        <div class="mx-auto w-full max-w-sm">
            <a href="{{ route('home') }}" class="block text-center text-2xl font-semibold tracking-tight">
                {{ config('app.name') }}
            </a>

            <div class="mt-8 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                {{ $slot }}
            </div>
        </div>
    </main>
</body>
</html>
