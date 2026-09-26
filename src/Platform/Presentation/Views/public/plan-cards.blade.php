{{--
    Las tarjetas de precio. RONDA-PLAN-MAESTRO.md sec. 3.6

    Todo lo que se ve sale de la tabla `plans`: precio, moneda, minimo de sedes,
    limites y funciones. Cambiar una tarifa es un UPDATE, no un despliegue, y la
    pagina no puede decir un numero distinto al que se cobra.
--}}
{{-- Las columnas van escritas y no calculadas: Tailwind genera las clases
     leyendo el codigo fuente, y una armada con PHP no existiria en el CSS. --}}
<div class="grid gap-6 md:grid-cols-2">
    @foreach ($plans as $plan)
        @php($limites = $plan->limits())

        <div @class([
            'rounded-2xl border p-6',
            'border-zinc-900 shadow-sm dark:border-zinc-100' => $loop->index === 1,
            'border-zinc-200 dark:border-zinc-800' => $loop->index !== 1,
        ])>
            @if ($loop->index === 1)
                <p class="mb-2 inline-block rounded bg-zinc-900 px-2 py-0.5 text-xs font-semibold text-white dark:bg-white dark:text-zinc-900">
                    {{ __('Most chosen') }}
                </p>
            @endif

            <h3 class="text-lg font-semibold">{{ $plan->name }}</h3>

            <p class="mt-3">
                <span class="text-3xl font-semibold">{{ $plan->currency }} {{ $plan->price_per_site }}</span>
                <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('per branch / month') }}</span>
            </p>

            @if ($plan->min_sites > 1)
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Minimum :count branches', ['count' => $plan->min_sites]) }}
                </p>
            @endif

            <ul class="mt-6 space-y-2 text-sm">
                <li>
                    {{ $limites->maxTemplates === null
                        ? __('Unlimited forms')
                        : __(':count forms', ['count' => $limites->maxTemplates]) }}
                </li>
                <li>{{ __('Unlimited people') }}</li>
                <li>
                    {{ $limites->storageGbPerSite === null
                        ? __('Storage without a cap')
                        : __(':gb GB of evidence per branch', ['gb' => $limites->storageGbPerSite]) }}
                </li>
                <li>{{ __('Works without signal, on any phone') }}</li>

                @foreach ($features as $bandera)
                    @if ($plan->includes($bandera))
                        <li>{{ $bandera->label() }}</li>
                    @endif
                @endforeach
            </ul>

            <a href="{{ route('register.tenant') }}"
               @class([
                   'mt-6 block rounded-md px-4 py-2 text-center text-sm font-medium',
                   'bg-zinc-900 text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200' => $loop->index === 1,
                   'border border-zinc-300 hover:border-zinc-900 dark:border-zinc-700 dark:hover:border-zinc-100' => $loop->index !== 1,
               ])>
                {{ __('Start :days free days', ['days' => $trialDays]) }}
            </a>
        </div>
    @endforeach
</div>
