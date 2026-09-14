<div class="max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">
            {{ $editing ? __('Edit site') : __('New site') }}
        </flux:heading>

        <flux:subheading>
            {{ __('The code identifies the site in the client systems and cannot be reused.') }}
        </flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="code" :label="__('Code')" required />
            <flux:input wire:model="name" :label="__('Name')" required />
        </div>

        <flux:select wire:model="zoneId" :label="__('Zone')" :placeholder="__('No zone')">
            @foreach ($zones as $zone)
                <flux:select.option value="{{ $zone->id }}">{{ $zone->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="address" :label="__('Address')" />

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="latitude" :label="__('Latitude')" inputmode="decimal" />
            <flux:input wire:model="longitude" :label="__('Longitude')" inputmode="decimal" />
        </div>

        <flux:select wire:model="timezone" :label="__('Time zone')" required>
            @foreach ($timezones as $tz)
                <flux:select.option value="{{ $tz }}">{{ $tz }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:fieldset>
            <flux:legend>{{ __('Opening hours') }}</flux:legend>

            <flux:description>
                {{ __('Local time at the site. A site in Iquitos and one in Lima may keep different hours.') }}
            </flux:description>

            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <flux:input type="time" wire:model="opensAt" :label="__('Opens at')" />
                <flux:input type="time" wire:model="closesAt" :label="__('Closes at')" />
            </div>
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>{{ __('Validity') }}</flux:legend>

            <flux:description>
                {{ __('A closed site is not deleted: it keeps its history.') }}
            </flux:description>

            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <flux:input type="date" wire:model="activeFrom" :label="__('Active from')" />
                <flux:input type="date" wire:model="activeUntil" :label="__('Active until')" />
            </div>
        </flux:fieldset>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">
                {{ $editing ? __('Save changes') : __('Create site') }}
            </flux:button>

            <flux:button variant="ghost" :href="route('sites.index')" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>
        </div>
    </form>
</div>
