{{--
    Precios. RONDA-PLAN-MAESTRO.md sec. 3.6

    Todo sale de la tabla `plans`. Lo unico escrito aqui son las respuestas a lo
    que se pregunta siempre, que no es el precio: es que pasa si abro una sede a
    mitad de mes y si me atan a un contrato.
--}}
@extends('platform::public.layout')

@section('title', __('Pricing'))

@section('content')
    <section class="mx-auto max-w-5xl px-6 py-16">
        <h1 class="text-3xl font-semibold tracking-tight">{{ __('Pricing') }}</h1>

        <p class="mt-3 max-w-2xl text-zinc-600 dark:text-zinc-400">
            {{ __('Per active branch, not per user: nobody should ration access to the people who do the work.') }}
        </p>

        <div class="mt-10">
            @include('platform::public.plan-cards', ['plans' => $plans, 'features' => $features, 'trialDays' => $trialDays])
        </div>

        <div class="mt-10 rounded-xl border border-zinc-200 p-6 dark:border-zinc-800">
            <h2 class="font-medium">{{ __('Bigger, or with something of your own?') }}</h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Dedicated instance, contractual SLA, integrations and on-premise if you need it. That one is quoted: write to us.') }}
            </p>
        </div>
    </section>

    <section class="border-t border-zinc-200 dark:border-zinc-800">
        <div class="mx-auto max-w-3xl px-6 py-16">
            <h2 class="text-2xl font-semibold tracking-tight">{{ __('What is always asked') }}</h2>

            <dl class="mt-8 space-y-8">
                <div>
                    <dt class="font-medium">{{ __('What counts as an active branch?') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('One that is loaded and receiving rounds. If you close one, it stops counting the next period; you do not have to warn anybody.') }}
                    </dd>
                </div>

                <div>
                    <dt class="font-medium">{{ __('Do you charge per user?') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('No. Put in everyone who works there: whoever delivers, whoever reviews and whoever watches. Charging per user ends with three people sharing one password.') }}
                    </dd>
                </div>

                <div>
                    <dt class="font-medium">{{ __('Is there a contract?') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('No. It is month to month. Paying yearly costs :months months less.', ['months' => $freeMonths]) }}
                    </dd>
                </div>

                <div>
                    <dt class="font-medium">{{ __('Do I need a card to try it?') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __(':days days, no card. If it does not fit you, you do nothing and it ends.', ['days' => $trialDays]) }}
                    </dd>
                </div>

                <div>
                    <dt class="font-medium">{{ __('What happens to my data if I leave?') }}</dt>
                    <dd class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('It is yours: you take it in Excel whenever you want, including the photos. Each client has their own separate database.') }}
                    </dd>
                </div>
            </dl>

            <a href="{{ route('register.tenant') }}"
               class="mt-10 inline-block rounded-md bg-zinc-900 px-5 py-3 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                {{ __('Start :days free days', ['days' => $trialDays]) }}
            </a>
        </div>
    </section>
@endsection
