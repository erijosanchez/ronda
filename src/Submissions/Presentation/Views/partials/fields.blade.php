{{--
    Los campos de un formulario para responder. Lo usan la entrega
    (SubmissionForm) y la correccion (SubmissionCorrectionForm), que tienen que
    verse y comportarse igual.

    Espera: $fields (list<Field>), $uploads (archivos nuevos por campo) y,
    opcional, $existingEvidence (cuantos archivos ya tiene cada campo, en una
    correccion).
--}}
@php
    $existingEvidence ??= [];
@endphp

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

            @case (\Ronda\Forms\Domain\ValueObjects\FieldType::Photo)
            @case (\Ronda\Forms\Domain\ValueObjects\FieldType::File)
                @php $esFoto = $tipo === \Ronda\Forms\Domain\ValueObjects\FieldType::Photo; @endphp

                <flux:field>
                    <flux:label>
                        {{ $field->label }}
                        @if ($field->required) <span class="text-red-600">*</span> @endif
                    </flux:label>

                    @if ($field->help)
                        <flux:description>{{ $field->help }}</flux:description>
                    @endif

                    @if (($existingEvidence[$field->key] ?? 0) > 0 && empty($uploads[$field->key]))
                        <flux:text class="text-xs">
                            {{ trans_choice('Keeps :count file already submitted. Upload new ones to replace it.|Keeps :count files already submitted. Upload new ones to replace them.', $existingEvidence[$field->key]) }}
                        </flux:text>
                    @endif

                    {{-- `capture` abre la camara trasera en el movil: una
                         foto de evidencia se toma en el momento, no se
                         elige de la galeria. --}}
                    <input
                        type="file"
                        multiple
                        wire:model="uploads.{{ $field->key }}"
                        @if ($esFoto) accept="image/jpeg,image/png,image/webp" capture="environment" @else accept="image/jpeg,image/png,image/webp,application/pdf,.xlsx,.docx" @endif
                        class="block w-full text-sm text-zinc-600 file:me-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium dark:text-zinc-300 dark:file:bg-zinc-700"
                    />

                    <div wire:loading wire:target="uploads.{{ $field->key }}">
                        <flux:text class="text-xs">{{ __('Uploading...') }}</flux:text>
                    </div>

                    @if (! empty($uploads[$field->key]))
                        <ul class="mt-2 flex flex-wrap gap-3">
                            @foreach ($uploads[$field->key] as $indice => $archivo)
                                <li wire:key="subida-{{ $field->key }}-{{ $indice }}" class="flex items-center gap-2 rounded-md border border-zinc-200 p-2 dark:border-zinc-700">
                                    @if ($esFoto && method_exists($archivo, 'isPreviewable') && $archivo->isPreviewable())
                                        <img src="{{ $archivo->temporaryUrl() }}" alt="" class="size-14 rounded object-cover" />
                                    @else
                                        <flux:icon.document class="size-6 text-zinc-400" />
                                    @endif

                                    <flux:text class="max-w-40 truncate text-xs">{{ $archivo->getClientOriginalName() }}</flux:text>

                                    <flux:button
                                        size="xs" variant="ghost" icon="x-mark"
                                        wire:click="removeUpload('{{ $field->key }}', {{ $indice }})"
                                        :aria-label="__('Remove file')"
                                    />
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @error('uploads.'.$field->key.'.*')
                        <flux:text class="mt-1 text-red-600">{{ $message }}</flux:text>
                    @enderror
                </flux:field>
                @break

            @case (\Ronda\Forms\Domain\ValueObjects\FieldType::Signature)
                <flux:field>
                    <flux:label>
                        {{ $field->label }}
                        @if ($field->required) <span class="text-red-600">*</span> @endif
                    </flux:label>

                    @if ($field->help)
                        <flux:description>{{ $field->help }}</flux:description>
                    @endif

                    @if (($existingEvidence[$field->key] ?? 0) > 0)
                        <flux:text class="text-xs">{{ __('Keeps the signature already submitted unless you sign again.') }}</flux:text>
                    @endif

                    {{-- wire:ignore: Livewire no debe redibujar el canvas
                         mientras se firma. El componente escribe la firma
                         en `signatures.<clave>` al levantar el trazo. --}}
                    <div wire:ignore x-data="signaturePad('signatures.{{ $field->key }}')" class="space-y-2">
                        <canvas
                            x-ref="canvas"
                            class="h-40 w-full touch-none rounded-md border border-dashed border-zinc-300 bg-white dark:border-zinc-600"
                        ></canvas>

                        <flux:button size="sm" variant="ghost" icon="arrow-path" x-on:click="clear()">
                            {{ __('Clear signature') }}
                        </flux:button>
                    </div>
                </flux:field>
                @break

            @default
                {{-- La tabla de filas necesita un editor propio. --}}
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
