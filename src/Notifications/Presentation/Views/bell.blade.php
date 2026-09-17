{{-- La campana de la cabecera. Se refresca sola: un aviso operativo que tarda
     un minuto en aparecer sigue siendo util. --}}
<div wire:poll.60s>
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="ghost" icon="bell" :aria-label="__('Notifications')">
            @if ($unread > 0)
                <flux:badge size="sm" color="red">{{ $unread > 99 ? '99+' : $unread }}</flux:badge>
            @endif
        </flux:button>

        <flux:menu class="w-80">
            <flux:menu.group :heading="__('Notifications')">
                @forelse ($latest as $aviso)
                    <flux:menu.item
                        wire:key="aviso-{{ $aviso->id }}"
                        :href="$aviso->data['url'] ?? route('notifications.index')"
                        wire:navigate
                    >
                        <div class="min-w-0">
                            <div class="truncate font-medium">{{ $aviso->data['title'] ?? '' }}</div>
                            <div class="truncate text-xs text-zinc-500">{{ $aviso->data['body'] ?? '' }}</div>
                        </div>
                    </flux:menu.item>
                @empty
                    <flux:menu.item disabled>{{ __('Nothing new') }}</flux:menu.item>
                @endforelse
            </flux:menu.group>

            <flux:menu.separator />

            <flux:menu.item :href="route('notifications.index')" wire:navigate icon="inbox">
                {{ __('See all') }}
            </flux:menu.item>
        </flux:menu>
    </flux:dropdown>
</div>
