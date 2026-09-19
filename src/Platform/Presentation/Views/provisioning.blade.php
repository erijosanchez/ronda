{{--
    «Preparando tu cuenta». RONDA-PLAN-MAESTRO.md sec. 15.3

    Pregunta cada dos segundos y deja de preguntar en cuanto esta lista: el
    `wire:poll` solo existe mientras no lo este.
--}}
<div @if (! $listo) wire:poll.2s="check" @endif>
    @if ($listo)
        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950">
                <svg class="h-6 w-6 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
            </div>

            <h1 class="mt-4 text-lg font-semibold">{{ __('Your account is ready') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __(':company now lives at its own address.', ['company' => $tenant->name]) }}
            </p>

            <a href="{{ $url }}"
               class="mt-6 block w-full rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                {{ __('Go in') }}
            </a>

            <p class="mt-4 break-all text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('Save this address:') }} {{ $url }}
            </p>
        </div>
    @else
        <div class="text-center">
            <svg class="mx-auto h-8 w-8 animate-spin text-zinc-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
            </svg>

            <h1 class="mt-4 text-lg font-semibold">{{ __('Preparing your account') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('We are setting up :company. This takes a few seconds; you can stay on this page.', ['company' => $tenant->name]) }}
            </p>

            @if ($tardando)
                <p class="mt-6 rounded-md bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                    {{ __('This is taking longer than usual. Keep this page open; if it does not finish, write to us and we will take a look.') }}
                </p>
            @endif
        </div>
    @endif
</div>
