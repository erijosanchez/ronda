<div class="max-w-3xl space-y-6">
    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    @php
        $zona = $submission->site?->timezone ?? 'UTC';
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $submission->templateVersion->template?->name }}</flux:heading>
            <flux:subheading>
                {{ $submission->site?->name }} ·
                {{ $submission->submitted_at->setTimezone($zona)->translatedFormat('j M Y, H:i') }} ·
                {{ $submission->author?->name }}
            </flux:subheading>
        </div>

        <div class="flex gap-2">
            @if ($submission->is_late)
                <flux:badge color="amber" size="sm">
                    {{ __('Late by :minutes min', ['minutes' => $submission->minutes_late]) }}
                </flux:badge>
            @else
                <flux:badge color="green" size="sm">{{ __('On time') }}</flux:badge>
            @endif

            <flux:badge size="sm" :color="match ($submission->state->getValue()) {
                'approved' => 'green',
                'rejected' => 'red',
                'under_review' => 'blue',
                default => 'zinc',
            }">
                {{ __('submission-states.'.$submission->state->getValue()) }}
            </flux:badge>

            <flux:badge color="zinc" size="sm">
                {{ __('Version :number', ['number' => $submission->templateVersion->number]) }}
            </flux:badge>
        </div>
    </div>

    <div class="space-y-5">
        @foreach ($fields as $field)
            @php
                $tipo = $field->type;
                $valor = $submission->data[$field->key] ?? null;
                $archivos = $attachments->get($field->key, collect());
            @endphp

            @if ($tipo === \Ronda\Forms\Domain\ValueObjects\FieldType::Section)
                <flux:separator :text="$field->label" />
                @continue
            @endif

            @if (! $tipo->isAnswerable())
                @continue
            @endif

            <div wire:key="respuesta-{{ $field->key }}">
                <flux:text class="text-xs uppercase tracking-wide">{{ $field->label }}</flux:text>

                @if ($archivos->isNotEmpty())
                    <ul class="mt-2 space-y-3">
                        @foreach ($archivos as $archivo)
                            <li wire:key="evidencia-{{ $archivo->id }}" class="flex flex-wrap items-start gap-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                @if (isset($urls[$archivo->id]) && $archivo->isImage())
                                    <a href="{{ $urls[$archivo->id] }}" target="_blank" rel="noopener">
                                        <img
                                            src="{{ $urls[$archivo->id] }}"
                                            alt="{{ $field->label }}"
                                            @class([
                                                'rounded object-cover',
                                                'size-28' => $archivo->kind !== \Ronda\Evidence\Domain\EvidenceKind::Signature,
                                                'h-20 w-48 bg-white object-contain' => $archivo->kind === \Ronda\Evidence\Domain\EvidenceKind::Signature,
                                            ])
                                        />
                                    </a>
                                @elseif (isset($urls[$archivo->id]))
                                    <flux:button size="sm" icon="arrow-down-tray" :href="$urls[$archivo->id]">
                                        {{ $archivo->original_name }}
                                    </flux:button>
                                @endif

                                <div class="min-w-0 flex-1 space-y-1 text-xs">
                                    <div class="flex flex-wrap gap-2">
                                        @if ($archivo->isFarFromSite())
                                            <flux:badge color="red" size="sm" icon="map-pin">
                                                {{ __(':meters m from the site', ['meters' => number_format($archivo->distance_meters)]) }}
                                            </flux:badge>
                                        @elseif ($archivo->distance_meters !== null)
                                            <flux:badge color="green" size="sm" icon="map-pin">
                                                {{ __(':meters m from the site', ['meters' => number_format($archivo->distance_meters)]) }}
                                            </flux:badge>
                                        @elseif ($archivo->kind !== \Ronda\Evidence\Domain\EvidenceKind::File)
                                            <flux:badge color="zinc" size="sm">{{ __('No location') }}</flux:badge>
                                        @endif

                                        @if ($archivo->isStaleAt($submission->submitted_at))
                                            <flux:badge color="amber" size="sm" icon="clock">{{ __('Taken more than a day before submitting') }}</flux:badge>
                                        @endif
                                    </div>

                                    @if ($archivo->captured_at)
                                        <flux:text class="text-xs">
                                            {{ __('Taken :date', ['date' => $archivo->captured_at->setTimezone($zona)->translatedFormat('j M Y, H:i')]) }}
                                        </flux:text>
                                    @endif

                                    @if ($archivo->location_source)
                                        <flux:text class="text-xs">
                                            {{ $archivo->location_source === 'exif' ? __('Location from the photo') : __('Location from the device') }}
                                        </flux:text>
                                    @endif

                                    @if ($archivo->kind === \Ronda\Evidence\Domain\EvidenceKind::Signature && $archivo->ip_address)
                                        <flux:text class="text-xs">{{ __('Signed from :ip', ['ip' => $archivo->ip_address]) }}</flux:text>
                                    @endif

                                    <flux:text class="break-all font-mono text-[11px]" title="SHA-256">
                                        SHA-256 {{ $archivo->sha256 }}
                                    </flux:text>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @elseif ($valor === null || $valor === '' || $valor === [])
                    <flux:text class="text-zinc-400">—</flux:text>
                @elseif (is_bool($valor))
                    <flux:text variant="strong">{{ $valor ? __('Yes') : __('No') }}</flux:text>
                @elseif (is_array($valor))
                    <flux:text variant="strong">{{ implode(', ', $valor) }}</flux:text>
                @else
                    <flux:text variant="strong" class="whitespace-pre-line">{{ $valor }}</flux:text>
                @endif
            </div>
        @endforeach
    </div>

    <flux:separator />

    <livewire:workflow.review-panel :submission="$submission" :key="'panel-'.$submission->id" />

    <div>
        <flux:button variant="ghost" icon="arrow-left" :href="route('submissions.pending')" wire:navigate>
            {{ __('Back to pending') }}
        </flux:button>
    </div>
</div>
