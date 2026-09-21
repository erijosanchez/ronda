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

    <div class="rounded-xl border border-dashed border-zinc-300 p-5 text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-400">
        {{ __('To see what the client sees, impersonation is coming: with a mandatory reason, a time limit, a visible banner and a notice to the owner.') }}
    </div>
</div>
