<x-layouts.guest :title="__('Log in')">
    <h1 class="text-lg font-semibold">{{ __('Log in') }}</h1>

    <x-auth.errors class="mt-4" />

    @if (session('status'))
        <p class="mt-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </p>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" required autofocus autocomplete="username"
                   value="{{ old('email') }}"
                   class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-800">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium">{{ __('Password') }}</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-800">
        </div>

        <div class="flex items-center justify-between">
            <label for="remember" class="flex items-center gap-2 text-sm">
                <input id="remember" name="remember" type="checkbox"
                       class="rounded border-zinc-300 dark:border-zinc-700">
                {{ __('Remember me') }}
            </label>

            <a href="{{ route('password.request') }}" class="text-sm underline underline-offset-4">
                {{ __('Forgot your password?') }}
            </a>
        </div>

        <button type="submit"
                class="w-full rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
            {{ __('Log in') }}
        </button>
    </form>
</x-layouts.guest>
