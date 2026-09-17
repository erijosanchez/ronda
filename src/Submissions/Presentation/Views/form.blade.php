<div class="max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">{{ $template?->name }}</flux:heading>
        <flux:subheading>{{ $site?->name }}</flux:subheading>
    </div>

    @error('obligation')
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.text>{{ $message }}</flux:callout.text>
        </flux:callout>
    @enderror

    {{-- Pide la ubicacion del dispositivo una vez y la deja en `latitude` y
         `longitude`. Si se niega, se entrega igual: la revision vera que no
         hay ubicacion. --}}
    <div x-data="deviceLocation" class="hidden" aria-hidden="true"></div>

    <form wire:submit="submit" class="space-y-6">
        @include('submissions::partials.fields')

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">{{ __('Submit report') }}</flux:button>
            <flux:button variant="ghost" :href="route('submissions.pending')" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
