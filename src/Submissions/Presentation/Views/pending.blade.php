<div class="space-y-6">
    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div>
        <flux:heading size="xl">{{ __('Pending today') }}</flux:heading>
        <flux:subheading>{{ __('What your sites have to report, sorted by deadline.') }}</flux:subheading>
    </div>

    @if ($toCorrect->isNotEmpty())
        <div class="space-y-3">
            <flux:heading size="lg">{{ __('Rejected, to correct') }}</flux:heading>

            <flux:table :paginate="$toCorrect">
                <flux:table.rows>
                    @foreach ($toCorrect as $envio)
                        <flux:table.row :key="'corregir-'.$envio->id">
                            <flux:table.cell variant="strong">{{ $envio->templateVersion?->template?->name }}</flux:table.cell>
                            <flux:table.cell>{{ $envio->site?->name }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="primary" :href="route('submissions.correct', $envio)" wire:navigate>
                                    {{ __('Correct') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    @if ($obligations->isEmpty())
        <flux:callout icon="check-badge">
            <flux:callout.heading>{{ __('Nothing pending') }}</flux:callout.heading>
            <flux:callout.text>{{ __('There is no report to submit right now for your sites.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:table :paginate="$obligations">
            <flux:table.columns>
                <flux:table.column>{{ __('Report') }}</flux:table.column>
                <flux:table.column>{{ __('Site') }}</flux:table.column>
                <flux:table.column>{{ __('Deadline') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($obligations as $obligation)
                    @php
                        // Las horas se muestran en la zona de la sede (sec. 8.6).
                        $zona = $obligation->site?->timezone ?? 'America/Lima';
                        $abierta = $obligation->opens_at->lessThanOrEqualTo($now);
                        $vencida = $obligation->due_at->lessThan($now);
                    @endphp

                    <flux:table.row :key="$obligation->id">
                        <flux:table.cell variant="strong">{{ $obligation->schedule?->template?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $obligation->site?->name }}</flux:table.cell>

                        <flux:table.cell>
                            {{ $obligation->due_at->timezone($zona)->format('d/m H:i') }}

                            @if ($vencida)
                                <flux:badge size="sm" color="amber">{{ __('Late') }}</flux:badge>
                            @elseif (! $abierta)
                                <flux:badge size="sm" color="zinc">
                                    {{ __('Opens :time', ['time' => $obligation->opens_at->timezone($zona)->format('H:i')]) }}
                                </flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            @if ($abierta)
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    :href="route('submissions.create', $obligation)"
                                    wire:navigate
                                >
                                    {{ __('Fill in') }}
                                </flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
