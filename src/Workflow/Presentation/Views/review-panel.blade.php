<div class="space-y-6">
    @error('review')
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.text>{{ $message }}</flux:callout.text>
        </flux:callout>
    @enderror

    {{-- Decision --}}
    @if ($canApprove || $canReject || $canTake)
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('Review') }}</flux:heading>

            <flux:textarea
                wire:model="decisionComment"
                :label="__('Comment')"
                :description="__('Required to reject: tell the site what to correct.')"
                rows="3"
            />

            <div class="flex flex-wrap gap-2">
                @if ($canApprove)
                    <flux:button variant="primary" icon="check" wire:click="approve">
                        {{ __('Approve') }}
                    </flux:button>
                @endif

                @if ($canReject)
                    <flux:button variant="danger" icon="x-mark" wire:click="reject">
                        {{ __('Reject') }}
                    </flux:button>
                @endif

                @if ($canTake)
                    <flux:button variant="ghost" icon="hand-raised" wire:click="take">
                        {{ __('Take for review') }}
                    </flux:button>
                @endif
            </div>
        </flux:card>
    @elseif ($takenByOther)
        <flux:callout icon="lock-closed">
            <flux:callout.text>
                {{ __(':name is reviewing this submission.', ['name' => $current->reviewer?->name ?? '—']) }}
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($canCorrect)
        <flux:callout variant="warning" icon="pencil-square">
            <flux:callout.heading>{{ __('This submission was rejected') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Correct it and it will go back to review.') }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" variant="primary" :href="route('submissions.correct', $current)" wire:navigate>
                    {{ __('Correct') }}
                </flux:button>
            </x-slot>
        </flux:callout>
    @endif

    {{-- Historial --}}
    <div>
        <flux:heading size="lg">{{ __('History') }}</flux:heading>

        <ol class="mt-3 space-y-3 border-s border-zinc-200 ps-4 dark:border-zinc-700">
            @foreach ($transitions as $transicion)
                <li wire:key="transicion-{{ $transicion->id }}">
                    <flux:text variant="strong">
                        {{ $transicion->from_state === 'rejected' ? __('Corrected') : __('workflow-actions.'.$transicion->to_state) }}
                        · {{ $transicion->actor?->name }}
                    </flux:text>
                    <flux:text class="text-xs">
                        {{ $transicion->created_at->setTimezone($current->site?->timezone ?? 'UTC')->translatedFormat('j M Y, H:i') }}
                    </flux:text>
                    @if ($transicion->comment)
                        <flux:text class="mt-1 whitespace-pre-line">{{ $transicion->comment }}</flux:text>
                    @endif
                </li>
            @endforeach
        </ol>

        @if ($revisions->isNotEmpty())
            <flux:text class="mt-3 text-xs">
                {{ trans_choice('This submission has been corrected :count time. Previous answers are kept for the record.|This submission has been corrected :count times. Previous answers are kept for the record.', $revisions->count()) }}
            </flux:text>
        @endif
    </div>

    {{-- Conversacion --}}
    <div>
        <flux:heading size="lg">{{ __('Comments') }}</flux:heading>

        <div class="mt-3 space-y-3">
            @forelse ($comments as $comentario)
                <div wire:key="comentario-{{ $comentario->id }}" class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                    <flux:text class="text-xs">
                        {{ $comentario->author?->name }} ·
                        {{ $comentario->created_at->setTimezone($current->site?->timezone ?? 'UTC')->translatedFormat('j M Y, H:i') }}
                    </flux:text>
                    <flux:text class="mt-1 whitespace-pre-line">{{ $comentario->body }}</flux:text>
                </div>
            @empty
                <flux:text class="text-zinc-400">{{ __('No comments yet.') }}</flux:text>
            @endforelse
        </div>

        @if ($canComment)
            <form wire:submit="comment" class="mt-3 space-y-2">
                <flux:textarea wire:model="newComment" :placeholder="__('Write a comment')" rows="2" />
                <flux:button type="submit" size="sm">{{ __('Send comment') }}</flux:button>
            </form>
        @endif
    </div>
</div>
