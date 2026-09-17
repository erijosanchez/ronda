<div class="max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl">
            {{ $editing ? __('Edit schedule') : __('New schedule') }}
        </flux:heading>

        <flux:subheading>
            {{ __('Changes apply to obligations that have not opened yet. What has already been submitted, missed or is open right now stays as it was.') }}
        </flux:subheading>
    </div>

    @error('schedule')
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.text>{{ $message }}</flux:callout.text>
        </flux:callout>
    @enderror

    <form wire:submit="save" class="space-y-8">
        {{-- Que se pide --}}
        <div class="space-y-4">
            <flux:select
                wire:model="templateId"
                :label="__('Template')"
                :placeholder="__('Choose a published template')"
                :disabled="$editing"
                :description="$editing ? __('A schedule keeps its template. To request another one, create another schedule.') : null"
                required
            >
                @foreach ($templates as $template)
                    <flux:select.option value="{{ $template->id }}">{{ $template->name }} ({{ $template->code }})</flux:select.option>
                @endforeach
            </flux:select>

            @if (! $editing && $templates->isEmpty())
                <flux:callout icon="document-text">
                    <flux:callout.text>{{ __('There are no published templates yet. Publish one before scheduling it.') }}</flux:callout.text>
                </flux:callout>
            @endif

            <flux:input wire:model="name" :label="__('Name')" :placeholder="__('E.g. Daily cash count')" required />
        </div>

        {{-- A quien --}}
        <flux:fieldset>
            <flux:legend>{{ __('Sites') }}</flux:legend>

            <flux:radio.group wire:model.live="scope" class="mt-2">
                @foreach ($scopes as $option)
                    <flux:radio value="{{ $option->value }}" :label="$option->label()" />
                @endforeach
            </flux:radio.group>

            @if ($scope === 'zone')
                <flux:select wire:model="zoneId" :label="__('Zone')" :placeholder="__('Choose a zone')" class="mt-4">
                    @foreach ($zones as $zone)
                        <flux:select.option value="{{ $zone->id }}">{{ $zone->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:description class="mt-2">
                    {{ __('Sites added to the zone later are included automatically.') }}
                </flux:description>
            @endif

            @if ($scope === 'sites' && $sites !== null)
                <div class="mt-4 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <flux:input
                            wire:model.live.debounce.300ms="siteSearch"
                            icon="magnifying-glass"
                            :placeholder="__('Search by name or code')"
                            class="max-w-xs"
                        />

                        <flux:badge size="sm">
                            {{ trans_choice(':count site selected|:count sites selected', count($siteIds)) }}
                        </flux:badge>
                    </div>

                    <flux:checkbox.group wire:model="siteIds">
                        @foreach ($sites as $site)
                            <flux:checkbox
                                wire:key="site-{{ $site->id }}"
                                value="{{ $site->id }}"
                                :label="$site->name"
                                :description="$site->code"
                            />
                        @endforeach
                    </flux:checkbox.group>

                    <flux:pagination :paginator="$sites" />

                    <flux:error name="siteIds" />
                </div>
            @endif
        </flux:fieldset>

        {{-- Cuando --}}
        <flux:fieldset>
            <flux:legend>{{ __('Repeat') }}</flux:legend>

            <div class="mt-2 grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="frequency" :label="__('Repeat')">
                    @foreach ($frequencies as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($frequency !== 'custom')
                    <flux:input
                        type="number"
                        min="1"
                        max="365"
                        wire:model.live.debounce.400ms="interval"
                        :label="__('Every')"
                        :description="match ($frequency) {
                            'weekly' => __('weeks'),
                            'monthly' => __('months'),
                            default => __('days'),
                        }"
                    />
                @endif
            </div>

            @if ($frequency === 'weekly')
                <flux:checkbox.group wire:model.live="weekdays" :label="__('On')" variant="buttons" class="mt-4">
                    @foreach ($weekdayOptions as $day)
                        <flux:checkbox value="{{ $day }}" :label="__('weekdays.'.$day)" />
                    @endforeach
                </flux:checkbox.group>
            @endif

            @if ($frequency === 'monthly')
                <flux:select wire:model.live="monthDay" :label="__('Day of the month')" class="mt-4">
                    @foreach (range(1, 31) as $day)
                        <flux:select.option value="{{ $day }}">{{ $day }}</flux:select.option>
                    @endforeach
                    <flux:select.option value="-1">{{ __('Last day of the month') }}</flux:select.option>
                </flux:select>

                <flux:description class="mt-2">
                    {{ __('Months without that day are skipped. To always hit month end, choose the last day.') }}
                </flux:description>
            @endif

            @if ($frequency === 'custom')
                <flux:input
                    wire:model.live.debounce.500ms="customRule"
                    :label="__('RRULE rule')"
                    :description="__('RFC 5545 format, without DTSTART. E.g. FREQ=MONTHLY;BYDAY=1MO for the first Monday of every month.')"
                    class="mt-4 font-mono"
                />
            @endif

            <div class="mt-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading size="sm">{{ __('Next dates') }}</flux:heading>

                @if ($preview === null)
                    <flux:text class="mt-1">{{ __('Complete the rule to see when it falls.') }}</flux:text>
                @elseif ($preview === [])
                    <flux:text class="mt-1">{{ __('This rule produces no upcoming dates.') }}</flux:text>
                @else
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($preview as $date)
                            <flux:badge size="sm" color="zinc">
                                {{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('D j M Y') }}
                            </flux:badge>
                        @endforeach
                    </div>
                @endif

                @if ($skipHolidays)
                    <flux:text class="mt-2 text-xs">{{ __('Holidays are skipped when obligations are created; this preview does not discount them.') }}</flux:text>
                @endif
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <flux:input type="date" wire:model.live="startsOn" :label="__('Starts on')" required />
                <flux:input type="date" wire:model.live="endsOn" :label="__('Ends on')" :description="__('Leave empty to keep it going.')" />
            </div>

            <flux:switch wire:model.live="skipHolidays" :label="__('Skip national holidays')" class="mt-4" />
        </flux:fieldset>

        {{-- En que horario --}}
        <flux:fieldset>
            <flux:legend>{{ __('Delivery window') }}</flux:legend>

            <flux:description>
                {{ __('Local time at each site. Submissions after the due time are late; after the tolerance, the obligation counts as missed.') }}
            </flux:description>

            <div class="mt-3 grid gap-4 sm:grid-cols-3">
                <flux:input type="time" wire:model="windowStart" :label="__('Opens at')" required />
                <flux:input type="time" wire:model="windowEnd" :label="__('Due at')" required />
                <flux:input type="number" min="0" max="1440" wire:model="toleranceMinutes" :label="__('Tolerance (minutes)')" required />
            </div>
        </flux:fieldset>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">
                {{ $editing ? __('Save changes') : __('Create schedule') }}
            </flux:button>

            <flux:button variant="ghost" :href="route('schedules.index')" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>
        </div>
    </form>
</div>
