{{--
    Ficha de un cliente. RONDA-PLAN-MAESTRO.md sec. 15.4

    Se CUENTA lo que tiene, no se lee lo que hay dentro: ningun reporte, ninguna
    foto. Para ver lo que ve el cliente esta la suplantacion, que queda
    registrada y avisa a su propietario.
--}}
<div class="space-y-6">
    <div>
        <a href="{{ route('back-office.tenants') }}" class="text-sm underline underline-offset-4" wire:navigate>
            {{ __('Back to clients') }}
        </a>

        <h1 class="mt-2 text-xl font-semibold tracking-tight">{{ $tenant->name }}</h1>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
            {{ $tenant->domains->first()?->domain ?? '—' }}
            · {{ $tenant->status->label() }}
            · {{ __('ID') }} <span class="font-mono text-xs">{{ $tenant->id }}</span>
        </p>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="font-medium">{{ __('Operation') }}</h2>

            @if ($usage === null)
                <p class="mt-2 text-sm text-amber-700 dark:text-amber-300">
                    {{ __('Its database is not available: either it is still being provisioned or something failed.') }}
                </p>
            @else
                <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Branches') }}</dt>
                        <dd class="mt-1 text-xl font-semibold">{{ $usage['sites'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('People') }}</dt>
                        <dd class="mt-1 text-xl font-semibold">{{ $usage['users'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Templates') }}</dt>
                        <dd class="mt-1 text-xl font-semibold">{{ $usage['templates'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Reports') }}</dt>
                        <dd class="mt-1 text-xl font-semibold">{{ $usage['submissions'] }}</dd>
                    </div>
                </dl>

                <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
                    @if ($usage['last'] === null)
                        {{ __('No report submitted yet.') }}
                    @else
                        {{ __('Last report: :when', ['when' => $usage['last']]) }}
                    @endif
                </p>
            @endif
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="font-medium">{{ __('Billing') }}</h2>

            @if ($subscription === null)
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('No subscription yet.') }}</p>
            @else
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $tenant->plan?->name ?? '—' }}
                    · {{ $subscription->status->label() }}
                    · {{ $subscription->cycle->label() }}
                </p>

                @if ($subscription->onTrial())
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Trial until :date', ['date' => $subscription->trial_ends_at?->format('d/m/Y')]) }}
                    </p>
                @endif

                @if ($invoices->isNotEmpty())
                    <table class="mt-4 w-full text-sm">
                        <tbody>
                            @foreach ($invoices as $cobro)
                                <tr class="border-t border-zinc-100 dark:border-zinc-800">
                                    <td class="py-1.5">{{ $cobro->period_start->format('m/Y') }}</td>
                                    <td class="py-1.5 text-right">{{ $cobro->money()->format() }}</td>
                                    <td class="py-1.5 text-right text-zinc-500">{{ $cobro->status->label() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @endif
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
        <h2 class="font-medium">{{ __('Enter the account') }}</h2>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('The client is notified right away, the session lasts :minutes minutes and everything is logged.', ['minutes' => $minutes]) }}
        </p>

        @if ($users === [])
            <p class="mt-4 text-sm text-amber-700 dark:text-amber-300">
                {{ __('There is nobody to enter as.') }}
            </p>
        @else
            <form wire:submit="impersonate" class="mt-4 space-y-4">
                <div>
                    <label for="impersonateUserId" class="block text-sm font-medium">{{ __('Enter as') }}</label>
                    <select id="impersonateUserId" wire:model="impersonateUserId" required
                            class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                        <option value="">{{ __('Choose someone') }}</option>
                        @foreach ($users as $persona)
                            <option value="{{ $persona['id'] }}">{{ $persona['label'] }}</option>
                        @endforeach
                    </select>
                    @error('impersonateUserId')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="reason" class="block text-sm font-medium">{{ __('Reason') }}</label>
                    <textarea id="reason" wire:model="reason" rows="2" required
                              placeholder="{{ __('Ticket 128: the report from yesterday does not show up in their inbox') }}"
                              class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800"></textarea>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('At least :min characters. The client reads this.', ['min' => $minReason]) }}
                    </p>
                    @error('reason')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                    {{ __('Enter the account') }}
                </button>
            </form>
        @endif

        @if ($history->isNotEmpty())
            <h3 class="mt-6 text-sm font-medium">{{ __('Previous entries') }}</h3>

            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($history as $entrada)
                    <li class="border-t border-zinc-100 pt-2 dark:border-zinc-800">
                        <span class="font-medium">{{ $entrada->platformUser?->name ?? '—' }}</span>
                        <span class="text-zinc-500 dark:text-zinc-400">
                            · {{ $entrada->started_at->timezone('America/Lima')->format('d/m/Y H:i') }}
                            · {{ $entrada->impersonated_user_email }}
                            @if ($entrada->ended_at === null)
                                · <span class="text-red-600 dark:text-red-400">{{ __('open') }}</span>
                            @endif
                        </span>
                        <p class="text-zinc-600 dark:text-zinc-400">{{ $entrada->reason }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
