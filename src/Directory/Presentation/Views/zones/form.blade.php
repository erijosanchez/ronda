<div class="max-w-xl space-y-6">
    <div>
        <flux:heading size="xl">{{ $editing ? __('Edit zone') : __('New zone') }}</flux:heading>
        <flux:subheading>{{ __('A zone groups sites. It can hang from another zone: country, region, district.') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="name" :label="__('Name')" required />

        <flux:select wire:model="parentId" :label="__('Inside zone')" :placeholder="__('Top level')">
            @foreach ($parents as $zona)
                <flux:select.option value="{{ $zona->id }}">{{ $zona->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select
            wire:model="managerId"
            :label="__('Manager')"
            :placeholder="__('Nobody yet')"
            :description="__('Informative: it does not grant permissions. Those are roles.')"
        >
            @foreach ($managers as $persona)
                <flux:select.option value="{{ $persona->id }}">{{ $persona->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">
                {{ $editing ? __('Save changes') : __('Create zone') }}
            </flux:button>

            <flux:button variant="ghost" :href="route('zones.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
