{{--
    Webhooks salientes. RONDA-PLAN-MAESTRO.md sec. 13.2

    Lo importante de esta pantalla no es el formulario: es el registro de
    entregas de abajo. Sin el, «no me llegó el aviso» es una discusión sin
    datos; con el, se ve qué se mandó, cuándo y qué respondió el otro lado.
--}}
<div class="max-w-4xl space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Webhooks') }}</flux:heading>
        <flux:subheading>
            {{ __('Ronda calls your system when something happens. Each call is signed so you can check it is ours.') }}
        </flux:subheading>
    </div>

    @if (! $inPlan)
        <flux:callout variant="warning" icon="lock-closed">
            <flux:callout.heading>{{ __('Webhooks are not included in your plan') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('You can register destinations, but Ronda will not call them until the plan includes the API.') }}
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" :href="route('plan')" wire:navigate>{{ __('See my plan') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($justCreatedSecret !== '')
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-900 dark:bg-emerald-950">
            <flux:heading size="lg">{{ __('Your signing secret') }}</flux:heading>
            <p class="mt-1 text-sm text-emerald-900 dark:text-emerald-200">
                {{ __('Copy it into your system. Each call carries a signature you verify with it.') }}
            </p>

            <p class="mt-3 break-all rounded-md bg-white p-3 font-mono text-sm dark:bg-zinc-900">
                {{ $justCreatedSecret }}
            </p>

            <p class="mt-3 text-xs text-emerald-900 dark:text-emerald-200">
                {{ __('Signature: HMAC-SHA256 of «timestamp.body», in X-Ronda-Signature. The timestamp is in X-Ronda-Timestamp.') }}
            </p>
        </div>
    @endif

    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
        <flux:heading size="lg">{{ __('New destination') }}</flux:heading>

        <form wire:submit="create" class="mt-4 space-y-4">
            <flux:input wire:model="url" :label="__('URL')" placeholder="https://tu-sistema.pe/ronda/avisos" required />

            <flux:input wire:model="description" :label="__('What is it for')" placeholder="{{ __('Our ERP') }}" />

            <div>
                <flux:label>{{ __('When to call') }}</flux:label>

                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach ($availableEvents as $evento)
                        <label class="flex items-start gap-2 text-sm">
                            <input type="checkbox" value="{{ $evento->value }}" wire:model="events"
                                   class="mt-0.5 rounded border-zinc-300 dark:border-zinc-700">
                            <span>
                                <span class="font-mono text-xs">{{ $evento->value }}</span>
                                <span class="block text-zinc-600 dark:text-zinc-400">{{ $evento->label() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                @error('events')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <flux:button type="submit" variant="primary">{{ __('Add destination') }}</flux:button>
        </form>
    </div>

    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
        <flux:heading size="lg">{{ __('Destinations') }}</flux:heading>

        @if ($endpoints->isEmpty())
            <flux:text class="mt-3">{{ __('None yet.') }}</flux:text>
        @else
            <ul class="mt-4 space-y-3">
                @foreach ($endpoints as $destino)
                    <li class="flex flex-wrap items-start justify-between gap-3 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                        <div class="min-w-0">
                            <p class="break-all font-mono text-sm">{{ $destino->url }}</p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $destino->description ?? '—' }}
                                · {{ implode(' · ', $destino->subscribed_events) }}
                            </p>

                            @if (! $destino->is_active)
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                    {{ $destino->exhausted()
                                        ? __('Switched off on its own after too many failures in a row.')
                                        : __('Switched off.') }}
                                </p>
                            @endif
                        </div>

                        <div class="flex items-center gap-2">
                            <flux:button size="sm" variant="ghost" wire:click="toggle({{ $destino->id }})">
                                {{ $destino->is_active ? __('Switch off') : __('Switch on') }}
                            </flux:button>

                            <flux:button size="sm" variant="ghost" wire:click="delete({{ $destino->id }})"
                                         wire:confirm="{{ __('Delete this destination? Its delivery log goes with it.') }}">
                                {{ __('Delete') }}
                            </flux:button>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-4">{{ $endpoints->links() }}</div>
        @endif
    </div>

    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
        <flux:heading size="lg">{{ __('Delivery log') }}</flux:heading>
        <flux:subheading>{{ __('What was sent, when, and what the other side answered.') }}</flux:subheading>

        @if ($deliveries->isEmpty())
            <flux:text class="mt-3">{{ __('Nothing sent yet.') }}</flux:text>
        @else
            <table class="mt-4 w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    <tr>
                        <th class="py-1">{{ __('Event') }}</th>
                        <th class="py-1">{{ __('When') }}</th>
                        <th class="py-1">{{ __('Attempts') }}</th>
                        <th class="py-1">{{ __('Result') }}</th>
                        <th class="py-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($deliveries as $entrega)
                        <tr class="border-t border-zinc-100 dark:border-zinc-800">
                            <td class="py-2 font-mono text-xs">{{ $entrega->event }}</td>
                            <td class="py-2">{{ $entrega->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="py-2">{{ $entrega->attempts }}</td>
                            <td class="py-2">
                                @if ($entrega->isDelivered())
                                    <span class="text-emerald-700 dark:text-emerald-400">{{ __('Delivered') }}</span>
                                @elseif ($entrega->hasFailed())
                                    <span class="text-red-600 dark:text-red-400">{{ __('Failed') }}</span>
                                @else
                                    <span class="text-amber-700 dark:text-amber-400">{{ __('Pending') }}</span>
                                @endif

                                @if ($entrega->error !== null)
                                    <span class="block text-xs text-zinc-500">{{ $entrega->error }}</span>
                                @endif
                            </td>
                            <td class="py-2 text-right">
                                <flux:button size="sm" variant="ghost" wire:click="resend({{ $entrega->id }})">
                                    {{ __('Resend') }}
                                </flux:button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="mt-4">{{ $deliveries->links() }}</div>
        @endif
    </div>
</div>
