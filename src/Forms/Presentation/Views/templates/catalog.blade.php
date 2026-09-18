<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Template catalog') }}</flux:heading>
        <flux:subheading>
            {{ __('Ready-made templates for the usual work. Install one and it becomes yours: edit it, version it and schedule it like any other.') }}
        </flux:subheading>
    </div>

    @error('catalog')
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.text>{{ $message }}</flux:callout.text>
        </flux:callout>
    @enderror

    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($catalog as $plantilla)
            @php $yaEsta = $installed->has($plantilla->value); @endphp

            <flux:card wire:key="catalogo-{{ $plantilla->value }}" class="flex flex-col gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading size="lg">{{ $plantilla->label() }}</flux:heading>

                        @if ($yaEsta)
                            <flux:badge size="sm" color="green">{{ __('Installed') }}</flux:badge>
                        @endif
                    </div>

                    <flux:text class="mt-1">{{ $plantilla->description() }}</flux:text>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <flux:badge size="sm" color="zinc" icon="calendar">{{ $plantilla->suggestedCadence() }}</flux:badge>
                    <flux:badge size="sm" color="zinc">
                        {{ trans_choice(':count field|:count fields', count($plantilla->schema())) }}
                    </flux:badge>
                    <span class="font-mono text-zinc-400">{{ $plantilla->value }}</span>
                </div>

                <flux:spacer />

                <div>
                    @if ($yaEsta)
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="pencil-square"
                            :href="route('templates.design', $installed->get($plantilla->value))"
                            wire:navigate
                        >
                            {{ __('Open template') }}
                        </flux:button>
                    @elseif ($canInstall)
                        <flux:button size="sm" variant="primary" icon="plus" wire:click="install('{{ $plantilla->value }}')">
                            {{ __('Install') }}
                        </flux:button>
                    @endif
                </div>
            </flux:card>
        @endforeach
    </div>

    <flux:button variant="ghost" icon="arrow-left" :href="route('templates.index')" wire:navigate>
        {{ __('Back to templates') }}
    </flux:button>
</div>
