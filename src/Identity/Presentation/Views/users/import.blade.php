{{--
    Importar personas. RONDA-PLAN-MAESTRO.md sec. 13.2

    Misma forma que la de sedes: analizar no escribe nada, confirmar escribe
    todo o nada, y el botón de confirmar no existe mientras haya problemas.
--}}
<div class="max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Import people') }}</flux:heading>
        <flux:subheading>
            {{ __('Upload a spreadsheet saved as CSV. Nothing is written until you confirm.') }}
        </flux:subheading>
    </div>

    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
        <flux:heading size="lg">{{ __('The file') }}</flux:heading>

        <flux:text class="mt-2">
            {{ __('Required columns: :required. Optional: :optional.', [
                'required' => 'nombre, correo',
                'optional' => 'telefono, roles, estado, sedes',
            ]) }}
        </flux:text>

        <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-zinc-600 dark:text-zinc-400">
            <li>{{ __('Nobody brings a password. New people get in with «I forgot my password», which emails them a link.') }}</li>
            <li>{{ __('Someone whose email already exists is updated, not duplicated. Their password is not touched.') }}</li>
            <li>{{ __('Several roles or sites in one cell, separated by | — for example :example.', ['example' => 'LIM-001|LIM-002']) }}</li>
            <li>{{ __('An empty «sedes» cell leaves their sites as they are. It does not take them away.') }}</li>
            <li>{{ __('The owner is not imported: their record is only changed by themselves.') }}</li>
        </ul>

        <flux:button size="sm" variant="ghost" icon="arrow-down-tray" wire:click="plantilla" class="mt-4">
            {{ __('Download template') }}
        </flux:button>
    </div>

    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
        <flux:input type="file" wire:model="file" :label="__('CSV file')" accept=".csv,text/csv" />

        @error('file')
            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror

        <div class="mt-4 flex items-center gap-3">
            <flux:button variant="primary" wire:click="analizar" wire:loading.attr="disabled">
                {{ __('Analyse') }}
            </flux:button>

            <flux:text size="sm" wire:loading wire:target="analizar">{{ __('Reading…') }}</flux:text>
        </div>
    </div>

    @if ($analyzed)
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('What will happen') }}</flux:heading>

            <div class="mt-3 flex flex-wrap gap-6">
                <div>
                    <p class="text-2xl font-semibold">{{ $toCreate }}</p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('new people') }}</p>
                </div>

                <div>
                    <p class="text-2xl font-semibold">{{ $toUpdate }}</p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('people updated') }}</p>
                </div>

                <div>
                    <p class="text-2xl font-semibold {{ $issues === [] ? '' : 'text-red-600 dark:text-red-400' }}">
                        {{ count($issues) }}
                    </p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('rows with problems') }}</p>
                </div>
            </div>

            @if ($issues === [])
                <flux:callout variant="success" icon="check-circle" class="mt-4">
                    <flux:callout.text>{{ __('The file is clean. Nothing has been written yet.') }}</flux:callout.text>
                </flux:callout>

                <flux:button variant="primary" wire:click="confirmar" wire:loading.attr="disabled" class="mt-4">
                    {{ __('Import') }}
                </flux:button>
            @else
                <flux:callout variant="warning" icon="exclamation-triangle" class="mt-4">
                    <flux:callout.text>
                        {{ __('Fix the file and upload it again. Nothing is imported while there are problems.') }}
                    </flux:callout.text>
                </flux:callout>

                <table class="mt-4 w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="w-20 py-1">{{ __('Row') }}</th>
                            <th class="py-1">{{ __('Problem') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($issues as $problema)
                            <tr class="border-t border-zinc-100 dark:border-zinc-800">
                                <td class="py-2 tabular-nums">{{ $problema['row'] }}</td>
                                <td class="py-2">{{ $problema['message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif
</div>
