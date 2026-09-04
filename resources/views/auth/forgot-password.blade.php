<x-layouts.guest :title="__('Forgot your password?')">
    <h1 class="text-lg font-semibold">{{ __('Forgot your password?') }}</h1>
    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('We will email you a link to choose a new one.') }}
    </p>

    <x-auth.errors class="mt-4" />

    @if (session('status'))
        <p class="mt-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}"
                   class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        </div>
        <button type="submit"
                class="w-full rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900">
            {{ __('Email password reset link') }}
        </button>
    </form>
</x-layouts.guest>
