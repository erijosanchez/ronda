<x-layouts.guest :title="__('Two-factor authentication')">
    <h1 class="text-lg font-semibold">{{ __('Two-factor authentication') }}</h1>
    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('Enter the code from your authenticator app, or one of your recovery codes.') }}
    </p>

    <x-auth.errors class="mt-4" />

    <form method="POST" action="{{ route('two-factor.login') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="code" class="block text-sm font-medium">{{ __('Authentication code') }}</label>
            <input id="code" name="code" type="text" inputmode="numeric" autofocus autocomplete="one-time-code"
                   class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
        </div>
        <div>
            <label for="recovery_code" class="block text-sm font-medium">{{ __('Recovery code') }}</label>
            <input id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code"
                   class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
        </div>

        <button type="submit"
                class="w-full rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-zinc-900">
            {{ __('Continue') }}
        </button>
    </form>
</x-layouts.guest>
