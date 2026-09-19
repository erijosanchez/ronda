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

    {{-- Entregas que esperan señal. Vive en el dispositivo, así que se pinta
         con Alpine y no con Livewire: el servidor no sabe que existen. --}}
    <div x-data="outboxQueue" x-cloak>
        <template x-if="envios.length > 0">
            <div class="space-y-3">
                <flux:callout variant="warning" icon="cloud-arrow-up">
                    <flux:callout.heading x-text="envios.length === 1
                        ? @js(__('1 report waiting to be sent'))
                        : envios.length + ' ' + @js(__('reports waiting to be sent'))"></flux:callout.heading>

                    <flux:callout.text>
                        {{ __('They are saved on this device and will be sent on their own when there is signal.') }}
                    </flux:callout.text>
                </flux:callout>

                <ul class="space-y-2">
                    <template x-for="envio in envios" :key="envio.token">
                        <li class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="min-w-0">
                                <flux:text variant="strong" x-text="envio.titulo"></flux:text>
                                <flux:text class="text-xs" x-text="envio.sede"></flux:text>
                                <template x-if="envio.rechazado">
                                    <flux:text class="text-xs text-red-600" x-text="envio.motivo"></flux:text>
                                </template>
                            </div>

                            <div class="flex gap-2">
                                <template x-if="! envio.rechazado">
                                    <flux:button size="sm" variant="ghost" icon="arrow-path" x-on:click="reintentar()">
                                        {{ __('Retry') }}
                                    </flux:button>
                                </template>

                                <template x-if="envio.rechazado">
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="trash"
                                        x-on:click="descartar(envio.token)"
                                    >
                                        {{ __('Discard') }}
                                    </flux:button>
                                </template>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>
        </template>
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
