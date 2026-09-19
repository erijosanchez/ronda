{{--
    Registro self-service. RONDA-PLAN-MAESTRO.md sec. 15.3

    Un solo formulario, seis campos y ninguna pregunta que se pueda responder
    despues: el sector, las sedes y los encargados se piden ya dentro, en el
    asistente de arranque, cuando la cuenta existe y se ve para que sirven.
--}}
<div>
    <h1 class="text-lg font-semibold">{{ __('Create your account') }}</h1>
    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
        {{ __('It takes a minute. You will have your own address and your first report ready today.') }}
    </p>

    <form wire:submit="register" class="mt-6 space-y-4">
        <div>
            <label for="company" class="block text-sm font-medium">{{ __('Company name') }}</label>
            <input id="company" type="text" required autofocus autocomplete="organization"
                   wire:model.live.debounce.500ms="company"
                   class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-800">
            @error('company')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="subdomain" class="block text-sm font-medium">{{ __('Your address') }}</label>
            <div class="mt-1 flex items-stretch rounded-md border border-zinc-300 shadow-sm focus-within:border-zinc-900 focus-within:ring-1 focus-within:ring-zinc-900 dark:border-zinc-700">
                <input id="subdomain" type="text" required autocomplete="off" spellcheck="false"
                       wire:model.blur="subdomain"
                       class="block w-full rounded-l-md border-0 bg-transparent px-3 py-2 text-sm focus:outline-none dark:bg-zinc-800">
                <span class="flex items-center rounded-r-md bg-zinc-100 px-3 text-sm text-zinc-500 dark:bg-zinc-700 dark:text-zinc-300">
                    .{{ $dominio }}
                </span>
            </div>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('This is where your team will sign in. It cannot be changed later.') }}
            </p>
            @error('subdomain')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="border-t border-zinc-200 pt-4 dark:border-zinc-800">
            <label for="ownerName" class="block text-sm font-medium">{{ __('Your name') }}</label>
            <input id="ownerName" type="text" required autocomplete="name"
                   wire:model="ownerName"
                   class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-800">
            @error('ownerName')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium">{{ __('Email') }}</label>
            <input id="email" type="email" required autocomplete="username"
                   wire:model.blur="email"
                   class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-800">
            @error('email')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium">{{ __('Password') }}</label>
            <input id="password" type="password" required autocomplete="new-password"
                   wire:model="password"
                   class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-800">
            @error('password')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium">{{ __('Confirm password') }}</label>
            <input id="password_confirmation" type="password" required autocomplete="new-password"
                   wire:model="password_confirmation"
                   class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-800">
        </div>

        <div>
            <label for="terms" class="flex items-start gap-2 text-sm">
                <input id="terms" type="checkbox" wire:model="terms"
                       class="mt-0.5 rounded border-zinc-300 dark:border-zinc-700">
                <span>{{ __('I accept the terms of service and the privacy policy.') }}</span>
            </label>
            @error('terms')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="register"
                class="w-full rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-60 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
            <span wire:loading.remove wire:target="register">{{ __('Create account') }}</span>
            <span wire:loading wire:target="register">{{ __('Creating your account...') }}</span>
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
        {{ __('Already have an account? Sign in at your own address.') }}
    </p>
</div>
