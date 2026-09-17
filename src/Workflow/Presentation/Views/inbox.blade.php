<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Review') }}</flux:heading>
        <flux:subheading>{{ __('Submissions from your sites waiting for a decision, oldest first.') }}</flux:subheading>
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <flux:radio.group wire:model.live="state" variant="segmented">
            @foreach ($states as $opcion)
                <flux:radio value="{{ $opcion }}" :label="__('submission-states.'.$opcion).' ('.$counts[$opcion].')'" />
            @endforeach
        </flux:radio.group>

        @if ($state !== 'submitted')
            <flux:switch wire:model.live="onlyMine" :label="__('Only mine')" />
        @endif

        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            :placeholder="__('Search by site or template')"
            class="max-w-xs"
        />
    </div>

    @if ($submissions->isEmpty())
        <flux:callout icon="inbox">
            <flux:callout.heading>{{ __('Nothing here') }}</flux:callout.heading>
            <flux:callout.text>{{ __('There are no submissions in this state for your sites.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:table :paginate="$submissions">
            <flux:table.columns>
                <flux:table.column>{{ __('Report') }}</flux:table.column>
                <flux:table.column>{{ __('Site') }}</flux:table.column>
                <flux:table.column>{{ __('Submitted') }}</flux:table.column>
                <flux:table.column>{{ __('By') }}</flux:table.column>
                <flux:table.column>{{ __('Reviewer') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($submissions as $envio)
                    @php $zona = $envio->site?->timezone ?? 'UTC'; @endphp

                    <flux:table.row :key="$envio->id">
                        <flux:table.cell variant="strong">
                            {{ $envio->templateVersion?->template?->name }}
                            @if ($envio->revision > 1)
                                <flux:badge size="sm" color="blue">{{ __('Corrected') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>{{ $envio->site?->name }}</flux:table.cell>

                        <flux:table.cell>
                            {{ $envio->submitted_at->setTimezone($zona)->format('d/m H:i') }}
                            @if ($envio->is_late)
                                <flux:badge size="sm" color="amber">{{ __('Late') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>{{ $envio->author?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $envio->reviewer?->name ?? '—' }}</flux:table.cell>

                        <flux:table.cell align="end">
                            <flux:button size="sm" :href="route('submissions.show', $envio)" wire:navigate>
                                {{ __('Open') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
