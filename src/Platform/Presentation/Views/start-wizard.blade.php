{{--
    Asistente de arranque. RONDA-PLAN-MAESTRO.md sec. 15.3

    Cinco pasos en orden. El que toca se ve; los demas esperan su turno sin
    esconderse, para que se sepa cuanto falta antes de empezar.
--}}
<div class="max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Get your account running') }}</flux:heading>
        <flux:subheading>
            {{ __('Five steps. Nothing here is final: everything can be changed later.') }}
        </flux:subheading>
    </div>

    <div>
        <div class="flex items-center justify-between text-sm">
            <span class="font-medium">
                {{ __(':done of :total done', ['done' => $avance->done(), 'total' => $avance->total()]) }}
            </span>
            <span class="text-zinc-500 dark:text-zinc-400">{{ $avance->percentage() }}%</span>
        </div>

        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
            <div class="h-full rounded-full bg-emerald-500 transition-all"
                 style="width: {{ $avance->percentage() }}%"></div>
        </div>
    </div>

    @if ($avance->finished())
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-900 dark:bg-emerald-950">
            <flux:heading size="lg">{{ __('You are up and running') }}</flux:heading>
            <p class="mt-1 text-sm text-emerald-900 dark:text-emerald-200">
                {{ __('From now on the pending items appear on their own and the dashboard fills up as reports come in.') }}
            </p>

            <flux:button class="mt-4" variant="primary" :href="route('panel')" wire:navigate>
                {{ __('Go to the dashboard') }}
            </flux:button>
        </div>
    @endif

    <ol class="space-y-3">
        @foreach ($pasos as $indice => $paso)
            <li @class([
                'rounded-xl border p-5',
                'border-zinc-900 dark:border-zinc-100' => $paso['current'],
                'border-zinc-200 dark:border-zinc-800' => ! $paso['current'],
                'opacity-60' => $paso['done'],
            ])>
                <div class="flex items-start gap-4">
                    <span @class([
                        'mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                        'bg-emerald-500 text-white' => $paso['done'],
                        'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => ! $paso['done'] && $paso['current'],
                        'bg-zinc-200 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' => ! $paso['done'] && ! $paso['current'],
                    ])>
                        @if ($paso['done'])
                            &check;
                        @else
                            {{ $indice + 1 }}
                        @endif
                    </span>

                    <div class="min-w-0 flex-1">
                        <flux:heading size="lg">{{ $paso['title'] }}</flux:heading>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $paso['description'] }}</p>

                        @if (! $paso['done'])
                            <flux:button
                                class="mt-3"
                                size="sm"
                                :variant="$paso['current'] ? 'primary' : 'ghost'"
                                :href="$paso['route']"
                                wire:navigate
                            >
                                {{ $paso['cta'] }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            </li>
        @endforeach
    </ol>
</div>
