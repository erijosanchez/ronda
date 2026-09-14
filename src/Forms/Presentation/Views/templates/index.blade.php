<div class="space-y-6">
    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Templates') }}</flux:heading>
            <flux:subheading>{{ __('What each site is asked to report.') }}</flux:subheading>
        </div>

        @if ($canManage)
            <flux:button variant="primary" icon="plus" :href="route('templates.create')" wire:navigate>
                {{ __('New template') }}
            </flux:button>
        @endif
    </div>

    <flux:input
        wire:model.live.debounce.300ms="search"
        icon="magnifying-glass"
        :placeholder="__('Search by name or code')"
        class="max-w-xs"
    />

    @if ($templates->isEmpty())
        <flux:callout icon="document-text">
            <flux:callout.heading>{{ __('No templates yet') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('A template defines what is asked at a site. Nothing can be scheduled until there is one.') }}
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:table :paginate="$templates">
            <flux:table.columns>
                <flux:table.column>{{ __('Code') }}</flux:table.column>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Version') }}</flux:table.column>
                <flux:table.column>{{ __('Fields') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($templates as $template)
                    <flux:table.row :key="$template->id">
                        <flux:table.cell class="font-mono text-xs">{{ $template->code }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ $template->name }}</flux:table.cell>

                        <flux:table.cell>
                            @if ($template->currentVersion)
                                {{ __('v:number', ['number' => $template->currentVersion->number]) }}
                                <span class="text-xs text-zinc-400">
                                    {{ __('of :count', ['count' => $template->versions_count]) }}
                                </span>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            {{ $template->currentVersion ? count($template->currentVersion->schema) : '—' }}
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($template->status->canBeScheduled())
                                <flux:badge size="sm" color="green">{{ $template->status->label() }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ $template->status->label() }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            @if ($canManage)
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    :href="route('templates.design', $template)"
                                    wire:navigate
                                >
                                    {{ __('Design') }}
                                </flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
