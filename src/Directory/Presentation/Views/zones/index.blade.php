<div class="space-y-6">
    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    @error('zone')
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.text>{{ $message }}</flux:callout.text>
        </flux:callout>
    @enderror

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Zones') }}</flux:heading>
            <flux:subheading>{{ __('How sites are grouped: region, district, cluster. A schedule can target a whole zone.') }}</flux:subheading>
        </div>

        @if ($canManage)
            <flux:button variant="primary" icon="plus" :href="route('zones.create')" wire:navigate>
                {{ __('New zone') }}
            </flux:button>
        @endif
    </div>

    <flux:input
        wire:model.live.debounce.300ms="search"
        icon="magnifying-glass"
        :placeholder="__('Search by name')"
        class="max-w-xs"
    />

    @if ($zones->isEmpty())
        <flux:callout icon="map">
            <flux:callout.heading>{{ __('No zones yet') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Zones are optional: sites work without them. They pay off when you want to schedule or measure by area.') }}
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:table :paginate="$zones">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Inside') }}</flux:table.column>
                <flux:table.column>{{ __('Manager') }}</flux:table.column>
                <flux:table.column>{{ __('Sites') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($zones as $zona)
                    <flux:table.row :key="$zona->id">
                        <flux:table.cell variant="strong">{{ $zona->name }}</flux:table.cell>
                        <flux:table.cell>{{ $zona->parent?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $zona->manager?->name ?? '—' }}</flux:table.cell>

                        <flux:table.cell>
                            {{ $zona->sites_count }}
                            @if ($zona->children_count > 0)
                                <span class="text-xs text-zinc-400">
                                    · {{ trans_choice(':count subzone|:count subzones', $zona->children_count) }}
                                </span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            @if ($canManage)
                                <div class="flex justify-end gap-1">
                                    <flux:button
                                        size="sm" variant="ghost" icon="pencil-square"
                                        :href="route('zones.edit', $zona)" wire:navigate
                                    >
                                        {{ __('Edit') }}
                                    </flux:button>

                                    <flux:button
                                        size="sm" variant="ghost" icon="trash"
                                        wire:click="delete({{ $zona->id }})"
                                        wire:confirm="{{ __('Delete this zone?') }}"
                                        :aria-label="__('Delete')"
                                    />
                                </div>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
