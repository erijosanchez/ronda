{{--
    Tokens de la API. RONDA-PLAN-MAESTRO.md sec. 13.1

    El token se ve UNA vez. En la base solo queda su hash, asi que no hay
    pantalla que pueda volver a mostrarlo, y se dice aqui para que nadie cierre
    la ventana creyendo que si.
--}}
<div class="max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl">{{ __('API access') }}</flux:heading>
        <flux:subheading>
            {{ __('To connect Ronda with your own systems. Each token does only what you allow it.') }}
        </flux:subheading>
    </div>

    @if (! $inPlan)
        <flux:callout variant="warning" icon="lock-closed">
            <flux:callout.heading>{{ __('The API is not included in your plan') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('You can create tokens, but they will not work until the plan includes the API.') }}
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" :href="route('plan')" wire:navigate>{{ __('See my plan') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    @if ($justCreated !== '')
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-900 dark:bg-emerald-950">
            <flux:heading size="lg">{{ __('Copy it now') }}</flux:heading>
            <p class="mt-1 text-sm text-emerald-900 dark:text-emerald-200">
                {{ __('This is the only time it is shown. If you lose it, revoke it and create another.') }}
            </p>

            <p class="mt-3 break-all rounded-md bg-white p-3 font-mono text-sm dark:bg-zinc-900">
                {{ $justCreated }}
            </p>
        </div>
    @endif

    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
        <flux:heading size="lg">{{ __('New token') }}</flux:heading>

        <form wire:submit="create" class="mt-4 space-y-4">
            <flux:input wire:model="name" :label="__('What is it for')" placeholder="{{ __('Integration with our ERP') }}" required />

            <div>
                <flux:label>{{ __('What it can do') }}</flux:label>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Give it the least it needs. A token that only reads cannot submit reports.') }}
                </p>

                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach ($availableScopes as $alcance)
                        <label class="flex items-start gap-2 text-sm">
                            <input type="checkbox" value="{{ $alcance->value }}" wire:model="scopes"
                                   class="mt-0.5 rounded border-zinc-300 dark:border-zinc-700">
                            <span>
                                <span class="font-mono text-xs">{{ $alcance->value }}</span>
                                <span class="block text-zinc-600 dark:text-zinc-400">{{ $alcance->label() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                @error('scopes')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <flux:button type="submit" variant="primary">{{ __('Create token') }}</flux:button>
        </form>
    </div>

    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
        <flux:heading size="lg">{{ __('Your tokens') }}</flux:heading>

        @if ($tokens->isEmpty())
            <flux:text class="mt-3">{{ __('You have not created any yet.') }}</flux:text>
        @else
            <ul class="mt-4 space-y-3">
                @foreach ($tokens as $token)
                    <li class="flex flex-wrap items-start justify-between gap-3 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                        <div class="min-w-0">
                            <p class="font-medium">{{ $token->name }}</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Created :date', ['date' => $token->created_at?->format('d/m/Y')]) }}
                                ·
                                @if ($token->last_used_at === null)
                                    {{ __('never used') }}
                                @else
                                    {{ __('last used :date', ['date' => $token->last_used_at->format('d/m/Y H:i')]) }}
                                @endif
                            </p>
                            <p class="mt-1 font-mono text-xs text-zinc-500 dark:text-zinc-400">
                                {{ implode(' · ', $token->abilities ?? []) }}
                            </p>
                        </div>

                        <flux:button size="sm" variant="ghost" wire:click="revoke({{ $token->id }})"
                                     wire:confirm="{{ __('Revoke this token? Whatever uses it stops working.') }}">
                            {{ __('Revoke') }}
                        </flux:button>
                    </li>
                @endforeach
            </ul>

            <div class="mt-4">{{ $tokens->links() }}</div>
        @endif
    </div>
</div>
