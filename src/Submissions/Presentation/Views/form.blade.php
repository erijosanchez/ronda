<div class="max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">{{ $template?->name }}</flux:heading>
        <flux:subheading>{{ $site?->name }}</flux:subheading>
    </div>

    @error('obligation')
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.text>{{ $message }}</flux:callout.text>
        </flux:callout>
    @enderror

    <form wire:submit="submit" class="space-y-6">
        @foreach ($fields as $field)
            @php
                $modelo = 'answers.'.$field->key;
                $tipo = $field->type;
            @endphp

            <div wire:key="campo-{{ $field->key }}">
                @switch ($tipo)
                    @case (\Ronda\Forms\Domain\ValueObjects\FieldType::Section)
                        <flux:separator :text="$field->label" />
                        @if ($field->help)
                            <flux:text class="mt-2">{{ $field->help }}</flux:text>
                        @endif
                        @break

                    @case (\Ronda\Forms\Domain\ValueObjects\FieldType::Text)
                        <flux:textarea wire:model="{{ $modelo }}" :label="$field->label" :description="$field->help" :required="$field->required" rows="2" />
                        @break

                    @case (\Ronda\Forms\Domain\ValueObjects\FieldType::Number)
                    @case (\Ronda\Forms\Domain\ValueObjects\FieldType::Money)
                        {{-- Texto con teclado numerico y no type=number: el navegador
                             redondea y reformatea, y un importe tiene que llegar tal
                             cual se escribio. --}}
                        <flux:input wire:model="{{ $modelo }}" inputmode="decimal" :label="$field->label" :description="$field->help" :required="$field->required" />
                        @break

                    @case (\Ronda\Forms\Domain\ValueObjects\FieldType::Date)
                        <flux:input type="date" wire:model="{{ $modelo }}" :label="$field->label" :description="$field->help" :required="$field->required" />
                        @break

                    @case (\Ronda\Forms\Domain\ValueObjects\FieldType::Time)
                        <flux:input type="time" wire:model="{{ $modelo }}" :label="$field->label" :description="$field->help" :required="$field->required" />
                        @break

                    @case (\Ronda\Forms\Domain\ValueObjects\FieldType::Boolean)
                        {{-- live: otros campos pueden depender de este. --}}
                        <flux:radio.group wire:model.live="{{ $modelo }}" :label="$field->label" :description="$field->help">
                            <flux:radio value="1" :label="__('Yes')" />
                            <flux:radio value="0" :label="__('No')" />
                        </flux:radio.group>
                        @break

                    @case (\Ronda\Forms\Domain\ValueObjects\FieldType::Select)
                        <flux:select wire:model.live="{{ $modelo }}" :label="$field->label" :description="$field->help" :placeholder="__('Choose one')">
                            @foreach ($field->options as $opcion)
                                <flux:select.option value="{{ $opcion }}">{{ $opcion }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @break

                    @case (\Ronda\Forms\Domain\ValueObjects\FieldType::MultiSelect)
                        <flux:checkbox.group wire:model.live="{{ $modelo }}" :label="$field->label" :description="$field->help">
                            @foreach ($field->options as $opcion)
                                <flux:checkbox :value="$opcion" :label="$opcion" />
                            @endforeach
                        </flux:checkbox.group>
                        @break

                    @case (\Ronda\Forms\Domain\ValueObjects\FieldType::Calculated)
                        {{-- Sin motor de formulas todavia: se muestra, no se calcula. --}}
                        @break

                    @default
                        {{-- Foto, archivo, firma y tabla llegan con el modulo Evidence. --}}
                        <flux:callout icon="clock" variant="secondary">
                            <flux:callout.heading>{{ $field->label }}</flux:callout.heading>
                            <flux:callout.text>{{ __('This field cannot be filled in yet.') }}</flux:callout.text>
                        </flux:callout>
                @endswitch

                @error($modelo)
                    <flux:text class="mt-1 text-red-600">{{ $message }}</flux:text>
                @enderror
            </div>
        @endforeach

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">{{ __('Submit report') }}</flux:button>
            <flux:button variant="ghost" :href="route('submissions.pending')" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
