<div class="space-y-6">
    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Schedules') }}</flux:heading>
            <flux:subheading>{{ __('When each template is requested and from which sites.') }}</flux:subheading>
        </div>

        @if ($canManage)
            <flux:button variant="primary" icon="plus" :href="route('schedules.create')" wire:navigate>
                {{ __('New schedule') }}
            </flux:button>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            :placeholder="__('Search by name or template')"
            class="max-w-xs"
        />

        <flux:switch wire:model.live="onlyActive" :label="__('Only active')" />
    </div>

    @if ($schedules->isEmpty())
        <flux:callout icon="calendar-days">
            <flux:callout.heading>{{ __('No schedules to show') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('A schedule turns a published template into obligations for the sites. Until there is one, nobody is asked for anything.') }}
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:table :paginate="$schedules">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Template') }}</flux:table.column>
                <flux:table.column>{{ __('Sites') }}</flux:table.column>
                <flux:table.column>{{ __('Repeat') }}</flux:table.column>
                <flux:table.column>{{ __('Window') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($schedules as $schedule)
                    <flux:table.row :key="$schedule->id">
                        <flux:table.cell variant="strong">{{ $schedule->name }}</flux:table.cell>

                        <flux:table.cell>{{ $schedule->template?->name ?? '—' }}</flux:table.cell>

                        <flux:table.cell>
                            @switch($schedule->scope)
                                @case(\Ronda\Scheduling\Domain\ScheduleScope::Zone)
                                    {{ __('Zone :zone', ['zone' => $schedule->zone?->name ?? '—']) }}
                                    @break
                                @case(\Ronda\Scheduling\Domain\ScheduleScope::Sites)
                                    {{ trans_choice(':count site|:count sites', $schedule->sites_count) }}
                                    @break
                                @default
                                    {{ $schedule->scope->label() }}
                            @endswitch
                        </flux:table.cell>

                        <flux:table.cell class="max-w-64 whitespace-normal">
                            {{ $schedule->recurrencePattern()->describe() }}
                        </flux:table.cell>

                        <flux:table.cell>
                            {{ substr($schedule->window_start, 0, 5) }}–{{ substr($schedule->window_end, 0, 5) }}
                            @if ($schedule->tolerance_minutes > 0)
                                <span class="text-xs text-zinc-400">
                                    {{ __('+:minutes min', ['minutes' => $schedule->tolerance_minutes]) }}
                                </span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if (! $schedule->active)
                                <flux:badge color="zinc" size="sm">{{ __('Paused') }}</flux:badge>
                            @elseif ($schedule->ends_on && $schedule->ends_on->isPast())
                                <flux:badge color="zinc" size="sm">{{ __('Ended') }}</flux:badge>
                            @else
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            @if ($canManage)
                                <div class="flex justify-end gap-1">
                                    @if ($schedule->active)
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="pause"
                                            wire:click="pause({{ $schedule->id }})"
                                            wire:confirm="{{ __('Pause this schedule? Its obligations that have not opened yet will be withdrawn.') }}"
                                        >
                                            {{ __('Pause') }}
                                        </flux:button>
                                    @else
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="play"
                                            wire:click="resume({{ $schedule->id }})"
                                        >
                                            {{ __('Resume') }}
                                        </flux:button>
                                    @endif

                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="pencil-square"
                                        :href="route('schedules.edit', $schedule)"
                                        wire:navigate
                                    >
                                        {{ __('Edit') }}
                                    </flux:button>
                                </div>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
