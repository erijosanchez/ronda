{{--
    Plan y consumo. RONDA-PLAN-MAESTRO.md sec. 3.6 y 15.1

    Solo lee: el cambio de plan llega con la facturacion. Lo que resuelve esta
    pantalla es que nadie descubra su limite chocando contra el.
--}}
<div class="max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Plan and usage') }}</flux:heading>
        <flux:subheading>
            {{ __('What your plan includes and how much of it you are using.') }}
        </flux:subheading>
    </div>

    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
        @if ($plan === null)
            <flux:heading size="lg">{{ __('Free trial') }}</flux:heading>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('No plan chosen yet, so nothing is capped. When the trial ends you pick one.') }}
            </p>
        @else
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <flux:heading size="lg">{{ $plan->name }}</flux:heading>

                @if ($monthly !== null && $plan->price_per_site > 0)
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __(':currency :amount per month', ['currency' => $plan->currency, 'amount' => $monthly]) }}
                    </span>
                @endif
            </div>

            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Billed per active branch: :currency :price each, minimum :min.', [
                    'currency' => $plan->currency,
                    'price' => $plan->price_per_site,
                    'min' => $plan->min_sites,
                ]) }}
            </p>
        @endif

        <dl class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Branches') }}</dt>
                <dd class="mt-1 text-2xl font-semibold">{{ $usage->sites }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('People') }}</dt>
                <dd class="mt-1 text-2xl font-semibold">{{ $usage->users }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Templates') }}</dt>
                <dd class="mt-1 text-2xl font-semibold">
                    {{ $usage->templates }}<span class="text-base font-normal text-zinc-500">
                        / {{ $limits->maxTemplates ?? __('no limit') }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Evidence') }}</dt>
                <dd class="mt-1 text-2xl font-semibold">
                    {{ number_format($usage->totalBytes() / 1024 ** 3, 2) }}<span class="text-base font-normal text-zinc-500"> GB</span>
                </dd>
            </div>
        </dl>
    </div>

    @if ($subscription !== null)
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:heading size="lg">{{ __('Billing') }}</flux:heading>

                <flux:badge size="sm" :color="$pastDue ? 'red' : 'zinc'">
                    {{ $subscription->status->label() }}
                </flux:badge>
            </div>

            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                @if ($subscription->onTrial())
                    {{ __('Your trial runs until :date. No card needed until then.', [
                        'date' => $subscription->trial_ends_at?->format('d/m/Y'),
                    ]) }}
                @elseif ($subscription->current_period_end !== null)
                    {{ __('Next charge on :date, :cycle.', [
                        'date' => $subscription->current_period_end->format('d/m/Y'),
                        'cycle' => mb_strtolower($subscription->cycle->label()),
                    ]) }}
                @endif
            </p>

            @if (! $subscription->canBeCharged())
                <p class="mt-3 rounded-md bg-zinc-100 p-3 text-sm text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                    {{ __('Payment is arranged by transfer for now. We will let you know when you can pay by card from here.') }}
                </p>
            @endif

            @if ($invoices->isNotEmpty())
                <table class="mt-4 w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="py-1">{{ __('Period') }}</th>
                            <th class="py-1">{{ __('Branches') }}</th>
                            <th class="py-1 text-right">{{ __('Amount') }}</th>
                            <th class="py-1 text-right">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $cobro)
                            <tr class="border-t border-zinc-100 dark:border-zinc-800">
                                <td class="py-2">{{ $cobro->period_start->format('d/m/Y') }}</td>
                                <td class="py-2">{{ $cobro->billed_sites }}</td>
                                <td class="py-2 text-right">{{ $cobro->money()->format() }}</td>
                                <td class="py-2 text-right">
                                    {{ $cobro->status->label() }}
                                    @if ($cobro->failure_reason !== null)
                                        <span class="block text-xs text-zinc-500">{{ $cobro->failure_reason }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
        <flux:heading size="lg">{{ __('Evidence per branch') }}</flux:heading>
        <flux:subheading>
            @if ($limits->storageGbPerSite === null)
                {{ __('Your plan does not cap storage.') }}
            @else
                {{ __('Your plan gives :gb GB to each branch.', ['gb' => $limits->storageGbPerSite]) }}
            @endif
        </flux:subheading>

        @if ($usage->storageBySite === [])
            <flux:text class="mt-4">{{ __('No branches yet.') }}</flux:text>
        @else
            <ul class="mt-4 space-y-3">
                @foreach ($usage->storageBySite as $sede)
                    @php($porcentaje = $sede->percentageOf($limits->storageBytesPerSite()))
                    <li>
                        <div class="flex items-center justify-between text-sm">
                            <span>{{ $sede->name }}</span>
                            <span class="text-zinc-500 dark:text-zinc-400">
                                {{ number_format($sede->gigabytes(), 2) }} GB
                                @if ($porcentaje !== null)
                                    · {{ $porcentaje }}%
                                @endif
                            </span>
                        </div>

                        @if ($porcentaje !== null)
                            <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                                <div @class([
                                    'h-full rounded-full',
                                    'bg-red-500' => $porcentaje >= 90,
                                    'bg-amber-500' => $porcentaje >= 70 && $porcentaje < 90,
                                    'bg-emerald-500' => $porcentaje < 70,
                                ]) style="width: {{ max($porcentaje, 1) }}%"></div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
        <flux:heading size="lg">{{ __('What your plan includes') }}</flux:heading>

        <ul class="mt-4 space-y-2">
            @foreach ($features as $bandera)
                <li class="flex items-center gap-2 text-sm">
                    @if ($bandera['included'])
                        <flux:badge size="sm" color="emerald">{{ __('Included') }}</flux:badge>
                    @else
                        <flux:badge size="sm" color="zinc">{{ __('Not in this plan') }}</flux:badge>
                    @endif

                    <span @class(['text-zinc-500 dark:text-zinc-400' => ! $bandera['included']])>
                        {{ $bandera['feature']->label() }}
                    </span>
                </li>
            @endforeach
        </ul>

        <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('To change plan, write to us. Self-service upgrades arrive with billing.') }}
        </p>
    </div>
</div>
