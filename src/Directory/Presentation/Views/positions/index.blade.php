<div class="space-y-6">
    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    @error('position')
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.text>{{ $message }}</flux:callout.text>
        </flux:callout>
    @enderror

    <div>
        <flux:heading size="xl">{{ __('Positions') }}</flux:heading>
        <flux:subheading>
            {{ __('The org chart of the client. A position describes the job; permissions come from roles.') }}
        </flux:subheading>
    </div>

    @if ($canManage)
        <flux:card>
            <form wire:submit="save" class="flex flex-wrap items-end gap-4">
                <flux:input wire:model="name" :label="__('Name')" :placeholder="__('E.g. Zone supervisor')" class="min-w-64" required />

                <flux:input
                    type="number" min="0" max="999"
                    wire:model="level"
                    :label="__('Level')"
                    :description="__('Higher is more senior. It orders the chart.')"
                    class="max-w-32"
                    required
                />

                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary">
                        {{ $editingId === null ? __('Add position') : __('Save changes') }}
                    </flux:button>

                    @if ($editingId !== null)
                        <flux:button variant="ghost" wire:click="cancel">{{ __('Cancel') }}</flux:button>
                    @endif
                </div>
            </form>
        </flux:card>
    @endif

    @if ($positions->isEmpty())
        <flux:callout icon="identification">
            <flux:callout.heading>{{ __('No positions yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Add the jobs your sites have: manager, supervisor, staff.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:table :paginate="$positions">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Level') }}</flux:table.column>
                <flux:table.column>{{ __('People') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($positions as $cargo)
                    <flux:table.row :key="$cargo->id">
                        <flux:table.cell variant="strong">{{ $cargo->name }}</flux:table.cell>
                        <flux:table.cell>{{ $cargo->level }}</flux:table.cell>
                        <flux:table.cell>{{ $cargo->assignments_count }}</flux:table.cell>

                        <flux:table.cell align="end">
                            @if ($canManage)
                                <div class="flex justify-end gap-1">
                                    <flux:button
                                        size="sm" variant="ghost" icon="pencil-square"
                                        wire:click="edit({{ $cargo->id }})"
                                    >
                                        {{ __('Edit') }}
                                    </flux:button>

                                    <flux:button
                                        size="sm" variant="ghost" icon="trash"
                                        wire:click="delete({{ $cargo->id }})"
                                        wire:confirm="{{ __('Delete this position?') }}"
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
