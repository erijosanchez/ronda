<div class="max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">
            {{ $editing ? __('Edit user') : __('New user') }}
        </flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="name" :label="__('Name')" required />

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="email" type="email" :label="__('Email')" required />
            <flux:input wire:model="phone" :label="__('Phone')" />
        </div>

        <flux:input
            wire:model="password"
            type="password"
            :label="__('Password')"
            :description="$editing
                ? __('Leave it blank to keep the current one.')
                : __('At least :min characters. It is checked against known breaches.', ['min' => config('security.password.min_length')])"
            :required="! $editing"
        />

        <flux:select wire:model="status" :label="__('Status')" required>
            @foreach ($allStatuses as $option)
                <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:fieldset>
            <flux:legend>{{ __('Roles') }}</flux:legend>

            <flux:description>
                {{ __('Roles grant permissions. Someone with no role can sign in but do nothing.') }}
            </flux:description>

            <flux:checkbox.group wire:model="roles" class="mt-3">
                @foreach ($allRoles as $role)
                    <flux:checkbox :value="$role->value" :label="$role->label()" />
                @endforeach
            </flux:checkbox.group>
        </flux:fieldset>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">
                {{ $editing ? __('Save changes') : __('Create user') }}
            </flux:button>

            <flux:button variant="ghost" :href="route('users.index')" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>
        </div>
    </form>
</div>
