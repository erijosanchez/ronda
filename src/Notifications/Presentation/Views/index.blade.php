<div class="max-w-3xl space-y-6">
    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Notifications') }}</flux:heading>
            <flux:subheading>{{ __('Reminders, missed reports and review decisions.') }}</flux:subheading>
        </div>

        @if ($unread > 0)
            <flux:button size="sm" icon="check" wire:click="markAllAsRead">
                {{ __('Mark all as read') }}
            </flux:button>
        @endif
    </div>

    <flux:switch wire:model.live="onlyUnread" :label="__('Only unread')" />

    @if ($notifications->isEmpty())
        <flux:callout icon="bell-slash">
            <flux:callout.heading>{{ __('Nothing to show') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Reminders and decisions about your sites will appear here.') }}</flux:callout.text>
        </flux:callout>
    @else
        <div class="space-y-3">
            @foreach ($notifications as $aviso)
                @php $datos = $aviso->data; @endphp

                <div
                    wire:key="aviso-{{ $aviso->id }}"
                    @class([
                        'rounded-lg border p-4',
                        'border-zinc-200 dark:border-zinc-700' => $aviso->read_at !== null,
                        'border-blue-300 bg-blue-50/50 dark:border-blue-800 dark:bg-blue-950/30' => $aviso->read_at === null,
                    ])
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <flux:heading size="sm">{{ $datos['title'] ?? '' }}</flux:heading>
                            <flux:text class="mt-1">{{ $datos['body'] ?? '' }}</flux:text>
                            <flux:text class="mt-1 text-xs">
                                {{ __('notification-topics.'.($datos['topic'] ?? '')) }} ·
                                {{ $aviso->created_at->diffForHumans() }}
                            </flux:text>
                        </div>

                        <div class="flex shrink-0 gap-2">
                            @if (! empty($datos['url']))
                                <flux:button size="sm" :href="$datos['url']" wire:navigate>{{ __('Open') }}</flux:button>
                            @endif

                            @if ($aviso->read_at === null)
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="check"
                                    wire:click="markAsRead('{{ $aviso->id }}')"
                                    :aria-label="__('Mark as read')"
                                />
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <flux:pagination :paginator="$notifications" />
    @endif
</div>
