<x-layouts.guest :title="__('Reset password')">
    <h1 class="text-lg font-semibold">{{ __('Reset password') }}</h1>

    <x-auth.errors class="mt-4" />

    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="block text-sm font-medium">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" required autofocus
                   value="{{ old('email', $request->email) }}"
                   class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
        </div>
        <div>
            <label for="password" class="block text-sm font-medium">{{ __('Password') }}</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium">{{ __('Confirm password') }}</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
        </div>

        <button type="submit"
                class="w-full rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-zinc-900">
            {{ __('Reset password') }}
        </button>
    </form>
</x-layouts.guest>
