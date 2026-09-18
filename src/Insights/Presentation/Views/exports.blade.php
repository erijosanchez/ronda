<div class="max-w-4xl space-y-6" @if ($running) wire:poll.10s @endif>
    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div>
        <flux:heading size="xl">{{ __('Exports') }}</flux:heading>
        <flux:subheading>
            {{ __('The file is generated in the background. You get a notification when it is ready to download.') }}
        </flux:subheading>
    </div>

    <flux:card>
        <form wire:submit="request" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input type="date" wire:model="from" :label="__('From')" required />
                <flux:input type="date" wire:model="to" :label="__('To')" required />
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:select wire:model="siteId" :label="__('Site')" :placeholder="__('All sites')">
                    @foreach ($sites as $sede)
                        <flux:select.option value="{{ $sede->id }}">{{ $sede->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select
                    wire:model="templateId"
                    :label="__('Template')"
                    :placeholder="__('All templates')"
                    :description="__('Pick one to get its reported fields as columns.')"
                >
                    @foreach ($templates as $plantilla)
                        <flux:select.option value="{{ $plantilla->id }}">{{ $plantilla->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="state" :label="__('Status')" :placeholder="__('Any status')">
                    @foreach ($states as $estado)
                        <flux:select.option value="{{ $estado }}">{{ __('submission-states.'.$estado) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:button type="submit" variant="primary" icon="arrow-down-tray">
                {{ __('Request export') }}
            </flux:button>
        </form>
    </flux:card>

    @if ($exports->isEmpty())
        <flux:callout icon="document-arrow-down">
            <flux:callout.heading>{{ __('No exports yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Ask for one above. Only you can download the files you request.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:table :paginate="$exports">
            <flux:table.columns>
                <flux:table.column>{{ __('Requested') }}</flux:table.column>
                <flux:table.column>{{ __('Period') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Rows') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($exports as $exportacion)
                    <flux:table.row :key="$exportacion->id">
                        <flux:table.cell>{{ $exportacion->created_at->translatedFormat('j M Y, H:i') }}</flux:table.cell>

                        <flux:table.cell>
                            {{ $exportacion->filters['from'] ?? '' }} → {{ $exportacion->filters['to'] ?? '' }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($exportacion->status->value) {
                                'completed' => 'green',
                                'failed' => 'red',
                                default => 'zinc',
                            }">
                                {{ $exportacion->status->label() }}
                            </flux:badge>

                            @if ($exportacion->error)
                                <flux:text class="mt-1 text-xs text-red-600">{{ $exportacion->error }}</flux:text>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>{{ $exportacion->rows ?? '—' }}</flux:table.cell>

                        <flux:table.cell align="end">
                            @if ($exportacion->status->isDownloadable())
                                <flux:button
                                    size="sm"
                                    icon="arrow-down-tray"
                                    :href="route('exports.download', $exportacion)"
                                >
                                    {{ __('Download') }}
                                </flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
