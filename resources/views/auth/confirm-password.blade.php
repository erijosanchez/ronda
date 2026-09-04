<x-layouts.guest :title="__('Confirm password')">
    <h1 class="text-lg font-semibold">{{ __('Confirm password') }}</h1>
    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('This is a sensitive area. Please confirm your password before continuing.') }}
    </p>

    <x-auth.errors class="mt-4" />

    <form method="POST" action="{{ route('password.confirm') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="password" class="block text-sm font-medium">{{ __('Password') }}</label>
            <input id="password" name="password" type="password" required autofocus autocomplete="current-password"
                   class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
        </div>
        <button type="submit"
                class="w-full rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-zinc-900">
            {{ __('Confirm') }}
        </button>
    </form>
</x-layouts.guest>
