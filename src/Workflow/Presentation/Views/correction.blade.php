<div class="max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Correct: :template', ['template' => $submission->templateVersion?->template?->name]) }}</flux:heading>
        <flux:subheading>{{ $submission->site?->name }}</flux:subheading>
    </div>

    @if ($rejectionComment)
        <flux:callout variant="warning" icon="chat-bubble-left-ellipsis">
            <flux:callout.heading>{{ __('Why it was rejected') }}</flux:callout.heading>
            <flux:callout.text class="whitespace-pre-line">{{ $rejectionComment }}</flux:callout.text>
        </flux:callout>
    @endif

    @error('submission')
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.text>{{ $message }}</flux:callout.text>
        </flux:callout>
    @enderror

    <div x-data="deviceLocation" class="hidden" aria-hidden="true"></div>

    <div x-data="submissionDraft('correccion-{{ $submission->id }}')">
        <div x-show="restored" x-cloak class="mb-4">
            <flux:callout icon="arrow-uturn-left">
                <flux:callout.text>{{ __('We restored what you had written on this device.') }}</flux:callout.text>
            </flux:callout>
        </div>

    <form wire:submit="submit" class="space-y-6">
        @include('submissions::partials.fields')

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">{{ __('Submit correction') }}</flux:button>
            <flux:button variant="ghost" :href="route('submissions.show', $submission)" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
    </div>
</div>
