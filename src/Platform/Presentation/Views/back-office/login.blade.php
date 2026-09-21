{{--
    Acceso al back-office de Ronda. RONDA-PLAN-MAESTRO.md sec. 15.4

    Tres pasos en la misma pantalla. El segundo factor no se puede saltar: esta
    cuenta entra a la operacion de cualquier cliente.
--}}
<div>
    @if ($step === 'credenciales')
        <h1 class="text-lg font-semibold">{{ __('Ronda support') }}</h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Internal access. Not for clients.') }}
        </p>

        <form wire:submit="submit" class="mt-6 space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium">{{ __('Email') }}</label>
                <input id="email" type="email" required autofocus autocomplete="username"
                       wire:model="email"
                       class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-800">
                @error('email')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium">{{ __('Password') }}</label>
                <input id="password" type="password" required autocomplete="current-password"
                       wire:model="password"
                       class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-800">
            </div>

            <button type="submit"
                    class="w-full rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                {{ __('Continue') }}
            </button>
        </form>
    @elseif ($step === 'configurar')
        <h1 class="text-lg font-semibold">{{ __('Set up your second factor') }}</h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('This account can enter any client. It cannot work without a second factor.') }}
        </p>

        <div class="mt-6 flex justify-center rounded-md bg-white p-4">
            <img src="{{ $qr }}" alt="{{ __('QR code for your authentication app') }}" width="192" height="192">
        </div>

        <p class="mt-3 break-all text-center text-xs text-zinc-500 dark:text-zinc-400">
            {{ __('If you cannot scan it, enter this key:') }} <span class="font-mono">{{ $secret }}</span>
        </p>

        <form wire:submit="confirmEnrollment" class="mt-6 space-y-4">
            <div>
                <label for="code" class="block text-sm font-medium">{{ __('Code from the app') }}</label>
                <input id="code" type="text" inputmode="numeric" autocomplete="one-time-code" required autofocus
                       wire:model="code"
                       class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-center font-mono text-lg tracking-widest shadow-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-800">
                @error('code')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="w-full rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                {{ __('Confirm') }}
            </button>
        </form>
    @elseif ($step === 'respaldo')
        <h1 class="text-lg font-semibold">{{ __('Save these codes') }}</h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Each one works once, if you lose your phone. They are not shown again.') }}
        </p>

        <ul class="mt-4 grid grid-cols-2 gap-2 rounded-md bg-zinc-100 p-4 font-mono text-sm dark:bg-zinc-800">
            @foreach ($recoveryCodes as $codigo)
                <li>{{ $codigo }}</li>
            @endforeach
        </ul>

        <button wire:click="finishEnrollment" type="button"
                class="mt-6 w-full rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
            {{ __('I saved them, go in') }}
        </button>
    @else
        <h1 class="text-lg font-semibold">{{ __('Second factor') }}</h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Enter the code from your app, or one of your recovery codes.') }}
        </p>

        <form wire:submit="challenge" class="mt-6 space-y-4">
            <div>
                <label for="code" class="block text-sm font-medium">{{ __('Code') }}</label>
                <input id="code" type="text" inputmode="numeric" autocomplete="one-time-code" required autofocus
                       wire:model="code"
                       class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-center font-mono text-lg tracking-widest shadow-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-800">
                @error('code')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="w-full rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                {{ __('Enter') }}
            </button>
        </form>
    @endif
</div>
