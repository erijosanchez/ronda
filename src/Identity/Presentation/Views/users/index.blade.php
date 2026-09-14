<div class="space-y-6">
    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    @error('delete')
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.text>{{ $message }}</flux:callout.text>
        </flux:callout>
    @enderror

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Users') }}</flux:heading>
            <flux:subheading>{{ __('The people who work in this account.') }}</flux:subheading>
        </div>

        @if ($canManage)
            <flux:button variant="primary" icon="plus" :href="route('users.create')" wire:navigate>
                {{ __('New user') }}
            </flux:button>
        @endif
    </div>

    <flux:input
        wire:model.live.debounce.300ms="search"
        icon="magnifying-glass"
        :placeholder="__('Search by name or email')"
        class="max-w-xs"
    />

    <flux:table :paginate="$users">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Roles') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell variant="strong">
                        {{ $user->name }}
                        @if ($user->id === $currentId)
                            <flux:badge size="sm" color="blue">{{ __('You') }}</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>{{ $user->email }}</flux:table.cell>

                    <flux:table.cell>
                        <div class="flex flex-wrap gap-1">
                            @forelse ($user->roles as $role)
                                <flux:badge size="sm" color="zinc">
                                    {{ \Ronda\Identity\Domain\RoleName::from($role->name)->label() }}
                                </flux:badge>
                            @empty
                                <span class="text-zinc-400">—</span>
                            @endforelse
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($user->status->canSignIn())
                            <flux:badge size="sm" color="green">{{ $user->status->label() }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="amber">{{ $user->status->label() }}</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        @if ($canManage)
                            <div class="flex justify-end gap-1">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="building-office"
                                    :href="route('users.sites', $user)"
                                    wire:navigate
                                >
                                    {{ __('Sites') }}
                                </flux:button>

                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    :href="route('users.edit', $user)"
                                    wire:navigate
                                >
                                    {{ __('Edit') }}
                                </flux:button>

                                @if ($user->id !== $currentId)
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="trash"
                                        wire:click="delete({{ $user->id }})"
                                        wire:confirm="{{ __('Remove :name from this account?', ['name' => $user->name]) }}"
                                    >
                                        {{ __('Remove') }}
                                    </flux:button>
                                @endif
                            </div>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
