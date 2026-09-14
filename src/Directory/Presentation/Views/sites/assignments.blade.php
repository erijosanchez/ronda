<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Sites for :name', ['name' => $user->name]) }}</flux:heading>

        <flux:subheading>
            {{ __('A person only sees the sites assigned here. The role applies to that site alone.') }}
        </flux:subheading>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            :placeholder="__('Search by name or code')"
            class="max-w-xs"
        />

        <flux:badge color="zinc">
            {{ __(':count assigned', ['count' => count($assignments)]) }}
        </flux:badge>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:table :paginate="$sites">
            <flux:table.columns>
                <flux:table.column />
                <flux:table.column>{{ __('Site') }}</flux:table.column>
                <flux:table.column>{{ __('Position') }}</flux:table.column>
                <flux:table.column>{{ __('Role at this site') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($sites as $site)
                    @php $asignada = array_key_exists($site->id, $assignments); @endphp

                    <flux:table.row :key="$site->id">
                        <flux:table.cell>
                            <flux:checkbox
                                :checked="$asignada"
                                wire:click="toggle({{ $site->id }})"
                                :aria-label="__('Assign :site', ['site' => $site->name])"
                            />
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            {{ $site->name }}
                            <span class="ms-1 font-mono text-xs text-zinc-400">{{ $site->code }}</span>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($asignada)
                                <flux:select
                                    wire:model="assignments.{{ $site->id }}.position_id"
                                    :placeholder="__('No position')"
                                    size="sm"
                                >
                                    @foreach ($positions as $position)
                                        <flux:select.option value="{{ $position->id }}">
                                            {{ $position->name }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($asignada)
                                <flux:select
                                    wire:model="assignments.{{ $site->id }}.role"
                                    :placeholder="__('No specific role')"
                                    size="sm"
                                >
                                    @foreach ($roles as $role)
                                        <flux:select.option value="{{ $role->value }}">
                                            {{ $role->label() }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">{{ __('Save assignments') }}</flux:button>

            <flux:button variant="ghost" :href="route('users.index')" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>
        </div>
    </form>
</div>
