<div class="space-y-6">
    <div>
        <flux:heading size="xl">
            {{ __('Hello, :name', ['name' => $userName]) }}
        </flux:heading>

        <flux:subheading>
            {{ __('You are working in :tenant.', ['tenant' => $tenantName]) }}
        </flux:subheading>

        {{-- Los roles se muestran siempre: saber con que sombrero se entra
             explica por que se ve una cosa y no otra. --}}
        @if ($roles === [])
            <flux:text class="mt-2">
                {{ __('You have no roles assigned yet. Ask whoever administers the account.') }}
            </flux:text>
        @else
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($roles as $role)
                    <flux:badge size="sm" color="zinc">{{ $role }}</flux:badge>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Mientras la cuenta esta a medio montar, lo primero que se ve es lo que
         falta. Desaparece solo en cuanto los cinco pasos estan hechos. --}}
    @if ($onboarding !== null)
        <flux:callout icon="rocket-launch">
            <flux:callout.heading>
                {{ __('Your account is not running yet') }}
            </flux:callout.heading>
            <flux:callout.text>
                {{ __(':done of :total steps done. The next one takes a couple of minutes.', [
                    'done' => $onboarding->done(),
                    'total' => $onboarding->total(),
                ]) }}
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" variant="primary" :href="route('onboarding')" wire:navigate>
                    {{ __('Continue setup') }}
                </flux:button>
            </x-slot>
        </flux:callout>
    @endif

    @if (! $showKpis)
        <flux:separator />

        <flux:callout icon="clipboard-document-check">
            <flux:callout.heading>{{ __('Your day') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Your pending reports for today are in the pending list.') }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" variant="primary" :href="route('submissions.pending')" wire:navigate>
                    {{ __('Pending today') }}
                </flux:button>
            </x-slot>
        </flux:callout>
    @else
        {{-- Filtros --}}
        <div class="flex flex-wrap items-end gap-4">
            <flux:radio.group wire:model.live="days" variant="segmented" :label="__('Period')">
                @foreach ($periods as $periodo)
                    <flux:radio value="{{ $periodo }}" :label="trans_choice('Last :count day|Last :count days', $periodo)" />
                @endforeach
            </flux:radio.group>

            <flux:select wire:model.live="siteId" :label="__('Site')" :placeholder="__('All sites')" class="max-w-52">
                @foreach ($sites as $sede)
                    <flux:select.option value="{{ $sede->id }}">{{ $sede->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="templateId" :label="__('Template')" :placeholder="__('All templates')" class="max-w-52">
                @foreach ($templates as $plantilla)
                    <flux:select.option value="{{ $plantilla->id }}">{{ $plantilla->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:text class="text-xs">
                {{ __('From :from to :to. Updated every hour.', ['from' => $from, 'to' => $to]) }}
            </flux:text>

            <flux:button size="sm" icon="arrow-down-tray" :href="route('exports.index')" wire:navigate>
                {{ __('Export') }}
            </flux:button>
        </div>

        {{-- Indicadores --}}
        @php
            // Un indicador sin base se pinta con un guion: «no habia nada que
            // medir» no es un cero.
            $porcentaje = fn (?float $valor): string => $valor === null ? '—' : number_format($valor, 1).' %';
            $minutos = fn (?float $valor): string => $valor === null ? '—' : number_format($valor, 0).' min';
        @endphp

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:card class="space-y-1">
                <flux:text class="text-xs uppercase tracking-wide">{{ __('Compliance') }}</flux:text>
                <flux:heading size="xl">{{ $porcentaje($summary->compliance()) }}</flux:heading>
                <flux:text class="text-xs">
                    {{ __(':fulfilled of :expected expected', ['fulfilled' => $summary->fulfilled, 'expected' => $summary->accountable()]) }}
                    @if ($summary->excused > 0)
                        · {{ trans_choice(':count excused|:count excused', $summary->excused) }}
                    @endif
                </flux:text>
            </flux:card>

            <flux:card class="space-y-1">
                <flux:text class="text-xs uppercase tracking-wide">{{ __('Punctuality') }}</flux:text>
                <flux:heading size="xl">{{ $porcentaje($summary->punctuality()) }}</flux:heading>
                <flux:text class="text-xs">
                    @if ($summary->late > 0)
                        {{ __(':count late, :minutes average delay', [
                            'count' => $summary->late,
                            'minutes' => $minutos($summary->averageMinutesLate()),
                        ]) }}
                    @else
                        {{ __('No late submissions') }}
                    @endif
                </flux:text>
            </flux:card>

            <flux:card class="space-y-1">
                <flux:text class="text-xs uppercase tracking-wide">{{ __('Quality') }}</flux:text>
                <flux:heading size="xl">{{ $porcentaje($summary->quality()) }}</flux:heading>
                <flux:text class="text-xs">
                    {{ __('Approved on the first try, out of :count decided', ['count' => $summary->approved + $summary->rejected]) }}
                </flux:text>
            </flux:card>

            <flux:card class="space-y-1">
                <flux:text class="text-xs uppercase tracking-wide">{{ __('Review time') }}</flux:text>
                <flux:heading size="xl">{{ $minutos($summary->averageReviewMinutes()) }}</flux:heading>
                <flux:text class="text-xs">
                    {{ trans_choice(':count decision in the period|:count decisions in the period', $summary->reviewsResolved) }}
                </flux:text>
            </flux:card>
        </div>

        {{-- Reincidencia --}}
        @if ($repeatOffenders !== [])
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>{{ __('Repeat offenders') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ trans_choice(
                        'A site failed to submit the same template :count or more times in this period.|Sites that failed to submit the same template :count or more times in this period.',
                        count($repeatOffenders),
                        ['count' => $repeatThreshold],
                    ) }}
                </flux:callout.text>

                <ul class="mt-2 space-y-1">
                    @foreach ($repeatOffenders as $caso)
                        <li>
                            <flux:text>
                                <span class="font-medium">{{ $caso['site_name'] }}</span>
                                · {{ $caso['template_name'] }}
                                · {{ trans_choice(':count miss|:count misses', $caso['missed']) }}
                            </flux:text>
                        </li>
                    @endforeach
                </ul>
            </flux:callout>
        @endif

        {{-- Ranking --}}
        <div class="space-y-3">
            <div>
                <flux:heading size="lg">{{ __('Sites to watch') }}</flux:heading>
                <flux:subheading>{{ __('Lowest compliance first: these are the ones to look at.') }}</flux:subheading>
            </div>

            @if ($ranking->isEmpty())
                <flux:callout icon="information-circle">
                    <flux:callout.heading>{{ __('No metrics yet') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Metrics appear once there are scheduled obligations in the period.') }}
                    </flux:callout.text>
                </flux:callout>
            @else
                <flux:table :paginate="$ranking">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Site') }}</flux:table.column>
                        <flux:table.column>{{ __('Compliance') }}</flux:table.column>
                        <flux:table.column>{{ __('Punctuality') }}</flux:table.column>
                        <flux:table.column>{{ __('Quality') }}</flux:table.column>
                        <flux:table.column>{{ __('Missed') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($ranking as $fila)
                            @php $resumen = $fila['summary']; @endphp

                            <flux:table.row :key="'ranking-'.$fila['site_id']">
                                <flux:table.cell variant="strong">{{ $fila['site_name'] }}</flux:table.cell>

                                <flux:table.cell>
                                    <flux:badge size="sm" :color="match (true) {
                                        $resumen->compliance() === null => 'zinc',
                                        $resumen->compliance() >= 95 => 'green',
                                        $resumen->compliance() >= 80 => 'amber',
                                        default => 'red',
                                    }">
                                        {{ $porcentaje($resumen->compliance()) }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell>{{ $porcentaje($resumen->punctuality()) }}</flux:table.cell>
                                <flux:table.cell>{{ $porcentaje($resumen->quality()) }}</flux:table.cell>
                                <flux:table.cell>{{ $resumen->missed }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    @endif
</div>
