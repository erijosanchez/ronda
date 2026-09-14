<div class="max-w-4xl space-y-6">
    <div>
        <flux:heading size="xl">
            {{ $editing ? __('Design template') : __('New template') }}
        </flux:heading>

        <flux:subheading>
            {{ __('Saving publishes a new version. The previous ones stay untouched, so past submissions keep reading as they did.') }}
        </flux:subheading>
    </div>

    @error('fields')
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.text>{{ $message }}</flux:callout.text>
        </flux:callout>
    @enderror

    <form wire:submit="publish" class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="code" :label="__('Code')" :disabled="$editing" required />
            <flux:input wire:model="name" :label="__('Name')" required />
        </div>

        <flux:textarea wire:model="description" :label="__('Description')" rows="2" />

        <flux:separator :text="__('Fields')" />

        <div class="space-y-4">
            @foreach ($fields as $index => $field)
                <flux:card wire:key="field-{{ $index }}" class="space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <flux:badge size="sm" color="zinc">{{ $index + 1 }}</flux:badge>

                        <div class="flex gap-1">
                            <flux:button
                                size="sm" variant="ghost" icon="chevron-up"
                                wire:click="moveUp({{ $index }})"
                                :aria-label="__('Move up')"
                                :disabled="$index === 0"
                            />
                            <flux:button
                                size="sm" variant="ghost" icon="chevron-down"
                                wire:click="moveDown({{ $index }})"
                                :aria-label="__('Move down')"
                                :disabled="$index === count($fields) - 1"
                            />
                            <flux:button
                                size="sm" variant="ghost" icon="trash"
                                wire:click="removeField({{ $index }})"
                                :aria-label="__('Remove field')"
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <flux:input
                            wire:model="fields.{{ $index }}.key"
                            :label="__('Key')"
                            :description="__('Used in exports and formulas.')"
                        />

                        <flux:input wire:model="fields.{{ $index }}.label" :label="__('Label')" />

                        <flux:select wire:model.live="fields.{{ $index }}.type" :label="__('Type')">
                            @foreach ($types as $type)
                                <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <flux:input wire:model="fields.{{ $index }}.help" :label="__('Help text')" />

                    @php $tipo = \Ronda\Forms\Domain\ValueObjects\FieldType::tryFrom($field['type']); @endphp

                    @if ($tipo?->hasOptions())
                        <flux:textarea
                            wire:model="fields.{{ $index }}.options"
                            :label="__('Options')"
                            :description="__('One per line.')"
                            rows="3"
                        />
                    @endif

                    <div class="flex flex-wrap gap-6">
                        @if ($tipo?->isAnswerable())
                            <flux:switch
                                wire:model="fields.{{ $index }}.required"
                                :label="__('Required')"
                            />
                        @endif

                        @if ($tipo?->isReportable())
                            <flux:switch
                                wire:model="fields.{{ $index }}.reportable"
                                :label="__('Reportable')"
                                :description="__('Feeds filters, metrics and exports.')"
                            />
                        @endif
                    </div>
                </flux:card>
            @endforeach
        </div>

        <flux:button variant="ghost" icon="plus" wire:click="addField" type="button">
            {{ __('Add field') }}
        </flux:button>

        <flux:separator />

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">
                {{ $editing ? __('Publish new version') : __('Publish template') }}
            </flux:button>

            <flux:button variant="ghost" :href="route('templates.index')" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>
        </div>
    </form>
</div>
