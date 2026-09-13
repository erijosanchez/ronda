<div class="space-y-6">
    <div>
        <flux:heading size="xl">
            {{ __('Hello, :name', ['name' => $userName]) }}
        </flux:heading>

        <flux:subheading>
            {{ __('You are working in :tenant.', ['tenant' => $tenantName]) }}
        </flux:subheading>
    </div>

    <flux:separator />

    <flux:card class="space-y-3">
        <flux:heading size="lg">{{ __('Your roles') }}</flux:heading>

        @if ($roles === [])
            <flux:text>
                {{ __('You have no roles assigned yet. Ask whoever administers the account.') }}
            </flux:text>
        @else
            <div class="flex flex-wrap gap-2">
                @foreach ($roles as $role)
                    <flux:badge color="zinc">{{ $role }}</flux:badge>
                @endforeach
            </div>
        @endif
    </flux:card>

    {{-- Sin sedes ni envíos todavía no hay nada que medir. Se dice, en vez de
         rellenar el panel con cifras inventadas. --}}
    <flux:callout icon="information-circle">
        <flux:callout.heading>{{ __('No metrics yet') }}</flux:callout.heading>

        <flux:callout.text>
            {{ __('Metrics will appear once there are sites and submissions on record.') }}
        </flux:callout.text>
    </flux:callout>
</div>
