{{--
    Menu de la persona que ha iniciado sesion.

    El cierre de sesion es un POST con token CSRF, no un enlace: un GET que
    cierra sesion lo puede disparar cualquier `<img>` de otro sitio.
--}}
@php
    $user = auth()->user();

    // Iniciales a partir del nombre, para el avatar. Con dos basta.
    $initials = collect(preg_split('/\s+/', trim((string) $user?->name)))
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<flux:dropdown position="top" align="end">
    <flux:profile :name="$user?->name" :initials="$initials" />

    <flux:menu>
        <flux:menu.group :heading="$user?->email">
            <flux:menu.item icon="user" disabled>
                {{ __('My profile') }}
            </flux:menu.item>
        </flux:menu.group>

        <flux:menu.separator />

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle">
                {{ __('Log out') }}
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>
