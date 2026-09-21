@php
    $suplantacion = session('impersonation');
@endphp

@if (is_array($suplantacion) && isset($suplantacion['expires_at']))
    {{--
        Banner de suplantacion. RONDA-PLAN-MAESTRO.md sec. 15.4

        Permanente y arriba del todo, no un aviso que se cierra: mientras haya
        alguien de Ronda dentro de la cuenta, tiene que verse. Dice quien mira,
        por que y hasta cuando, y deja salir de un clic.

        Se pinta en el layout de la aplicacion del cliente, asi que tambien lo
        ve el usuario suplantado si estuviera delante: es su cuenta.
    --}}
    <div class="sticky top-0 z-50 bg-red-600 text-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm">
            <p>
                <span class="font-semibold">{{ __('Ronda support is inside this account.') }}</span>
                <span class="opacity-90">
                    {{ __('Reason: :reason', ['reason' => $suplantacion['reason'] ?? '—']) }}
                    ·
                    {{ __('Until :time', [
                        'time' => \Illuminate\Support\Carbon::parse($suplantacion['expires_at'])
                            ->timezone(config('app.timezone_display', 'America/Lima'))
                            ->format('H:i'),
                    ]) }}
                </span>
            </p>

            <form method="POST" action="{{ route('impersonation.leave') }}">
                @csrf
                <button type="submit" class="rounded bg-white/20 px-3 py-1 font-medium hover:bg-white/30">
                    {{ __('Leave the account') }}
                </button>
            </form>
        </div>
    </div>
@endif
