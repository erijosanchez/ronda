{{--
    Portada. RONDA-PLAN-MAESTRO.md sec. 15.3

    Escrita para quien manda en una empresa con sucursales y hoy se entera de
    lo que pasó en cada una por WhatsApp, tarde y a medias. No se explica el
    producto por sus funciones: se explica el problema.
--}}
@extends('platform::public.layout')

@section('title', __('Operational control for branches'))

@section('content')
    <section class="mx-auto max-w-5xl px-6 py-20">
        <h1 class="max-w-3xl text-4xl font-semibold tracking-tight sm:text-5xl">
            {{ __('Know what happened in every branch, today.') }}
        </h1>

        <p class="mt-6 max-w-2xl text-lg text-zinc-600 dark:text-zinc-400">
            {{ __('Ronda turns the checks each branch owes into a list with a deadline, collects them with photo and location, and tells you which ones are missing before you have to ask.') }}
        </p>

        <div class="mt-10 flex flex-wrap items-center gap-4">
            <a href="{{ route('register.tenant') }}"
               class="rounded-md bg-zinc-900 px-5 py-3 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                {{ __('Start :days free days', ['days' => $trialDays]) }}
            </a>

            <a href="{{ route('pricing') }}" class="text-sm underline underline-offset-4">
                {{ __('See pricing') }}
            </a>

            <span class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('No card needed.') }}
            </span>
        </div>
    </section>

    <section class="border-y border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mx-auto max-w-5xl px-6 py-16">
            <h2 class="text-2xl font-semibold tracking-tight">{{ __('How it works') }}</h2>

            <ol class="mt-8 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                <li>
                    <p class="text-sm font-semibold text-zinc-400">01</p>
                    <h3 class="mt-1 font-medium">{{ __('You say what has to be checked') }}</h3>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Cash count, opening, deposit, cleaning, incident. Five ready-made forms; you change whatever you want.') }}
                    </p>
                </li>
                <li>
                    <p class="text-sm font-semibold text-zinc-400">02</p>
                    <h3 class="mt-1 font-medium">{{ __('And when') }}</h3>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Daily, weekly or monthly. Each round opens and closes at the hour of its own branch.') }}
                    </p>
                </li>
                <li>
                    <p class="text-sm font-semibold text-zinc-400">03</p>
                    <h3 class="mt-1 font-medium">{{ __('Your team delivers from the phone') }}</h3>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('With photo, location and signature. Without signal it waits on the phone and sends itself when it comes back.') }}
                    </p>
                </li>
                <li>
                    <p class="text-sm font-semibold text-zinc-400">04</p>
                    <h3 class="mt-1 font-medium">{{ __('You see it before asking') }}</h3>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Who delivered, who did not, who was late. And the system escalates what nobody solved.') }}
                    </p>
                </li>
            </ol>
        </div>
    </section>

    <section class="mx-auto max-w-5xl px-6 py-16">
        <h2 class="text-2xl font-semibold tracking-tight">{{ __('Why this and not a form or a group chat') }}</h2>

        <div class="mt-8 grid gap-8 sm:grid-cols-3">
            <div>
                <h3 class="font-medium">{{ __('A form does not chase anyone') }}</h3>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Here what is not delivered has a name, an hour and a consequence: it is reminded, it is missed, and it goes up to whoever has to know.') }}
                </p>
            </div>
            <div>
                <h3 class="font-medium">{{ __('Evidence that holds up') }}</h3>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Photos with date, location and a fingerprint of the file, stored privately. Nobody sees what their role does not allow.') }}
                </p>
            </div>
            <div>
                <h3 class="font-medium">{{ __('Made for a phone with bad signal') }}</h3>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('The report is filled and kept on the device. Nothing is lost and nothing is sent twice.') }}
                </p>
            </div>
        </div>
    </section>

    <section class="border-t border-zinc-200 dark:border-zinc-800">
        <div class="mx-auto max-w-5xl px-6 py-16">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h2 class="text-2xl font-semibold tracking-tight">{{ __('Pricing') }}</h2>
                <a href="{{ route('pricing') }}" class="text-sm underline underline-offset-4">
                    {{ __('See what each plan includes') }}
                </a>
            </div>

            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Per active branch, not per user: nobody should ration access to the people who do the work.') }}
            </p>

            <div class="mt-8">
                @include('platform::public.plan-cards', ['plans' => $plans, 'features' => $features, 'trialDays' => $trialDays])
            </div>
        </div>
    </section>
@endsection
