<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Sites') }}</flux:heading>
            <flux:subheading>{{ __('The branches where rounds are carried out.') }}</flux:subheading>
        </div>

        @if ($canCreate)
            <flux:button variant="primary" icon="plus" disabled>
                {{ __('New site') }}
            </flux:button>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            :placeholder="__('Search by name or code')"
            class="max-w-xs"
        />

        <flux:switch wire:model.live="onlyActive" :label="__('Only active')" />
    </div>

    @if ($sites->isEmpty())
        <flux:callout icon="building-office">
            <flux:callout.heading>{{ __('No sites to show') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('You only see the sites assigned to you. If you expect to see more, ask whoever administers the account.') }}
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:table :paginate="$sites">
            <flux:table.columns>
                <flux:table.column>{{ __('Code') }}</flux:table.column>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Zone') }}</flux:table.column>
                <flux:table.column>{{ __('Hours') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($sites as $site)
                    <flux:table.row :key="$site->id">
                        <flux:table.cell class="font-mono text-xs">{{ $site->code }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ $site->name }}</flux:table.cell>
                        <flux:table.cell>{{ $site->zone?->name ?? '—' }}</flux:table.cell>

                        <flux:table.cell>
                            @if ($site->opens_at && $site->closes_at)
                                {{ substr($site->opens_at, 0, 5) }}–{{ substr($site->closes_at, 0, 5) }}
                                <span class="text-xs text-zinc-400">{{ $site->timezone }}</span>
                            @else
                                —
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($site->active_until && $site->active_until->isPast())
                                <flux:badge color="zinc" size="sm">{{ __('Closed') }}</flux:badge>
                            @else
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
