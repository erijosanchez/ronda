{{--
    Clientes de Ronda. RONDA-PLAN-MAESTRO.md sec. 15.4

    Solo datos de la base central: entrar en la base de cada cliente para
    pintar una fila serian cien conexiones por pantalla.
--}}
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">{{ __('Clients') }}</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __(':total in total', ['total' => $tenants->total()]) }}
                @foreach ($states as $estado => $cuantos)
                    · {{ $estado }}: {{ $cuantos }}
                @endforeach
            </p>
        </div>

        <div class="flex items-end gap-3">
            <div>
                <label for="search" class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">
                    {{ __('Search') }}
                </label>
                <input id="search" type="search" wire:model.live.debounce.400ms="search"
                       placeholder="{{ __('Name or address') }}"
                       class="mt-1 block w-56 rounded-md border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
            </div>

            <div>
                <label for="status" class="block text-xs font-medium text-zinc-600 dark:text-zinc-400">
                    {{ __('State') }}
                </label>
                <select id="status" wire:model.live="status"
                        class="mt-1 block rounded-md border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($states->keys() as $estado)
                        <option value="{{ $estado }}">{{ $estado }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <table class="w-full text-sm">
            <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="px-4 py-2">{{ __('Client') }}</th>
                    <th class="px-4 py-2">{{ __('Address') }}</th>
                    <th class="px-4 py-2">{{ __('State') }}</th>
                    <th class="px-4 py-2">{{ __('Plan') }}</th>
                    <th class="px-4 py-2">{{ __('Since') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tenants as $cliente)
                    <tr class="border-t border-zinc-100 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800">
                        <td class="px-4 py-2">
                            <a href="{{ route('back-office.tenant', $cliente) }}"
                               class="font-medium underline underline-offset-4" wire:navigate>
                                {{ $cliente->name }}
                            </a>
                        </td>
                        <td class="px-4 py-2 text-zinc-600 dark:text-zinc-400">
                            {{ $cliente->domains->first()?->domain ?? '—' }}
                        </td>
                        <td class="px-4 py-2">
                            @if (! $cliente->isReady())
                                <span class="rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                    {{ __('Provisioning') }}
                                </span>
                            @else
                                {{ $cliente->status->label() }}
                            @endif
                        </td>
                        <td class="px-4 py-2 text-zinc-600 dark:text-zinc-400">{{ $cliente->plan?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-zinc-600 dark:text-zinc-400">
                            {{ $cliente->created_at?->format('d/m/Y') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-zinc-500 dark:text-zinc-400">
                            {{ __('No clients match.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $tenants->links() }}
</div>
