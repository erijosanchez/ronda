@props(['bag' => 'default'])

@if ($errors->{$bag}->any())
    <div {{ $attributes->merge(['class' => 'rounded-md bg-red-50 p-3 dark:bg-red-950']) }} role="alert">
        <ul class="list-inside list-disc space-y-1 text-sm text-red-800 dark:text-red-200">
            @foreach ($errors->{$bag}->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
