<?php

declare(strict_types=1);

namespace Ronda\Directory\Presentation\Livewire;

use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Ronda\Directory\Application\Actions\CreateSite;
use Ronda\Directory\Application\Actions\UpdateSite;
use Ronda\Directory\Application\Data\SiteData;
use Ronda\Directory\Domain\Models\Site;
use Ronda\Directory\Domain\Models\Zone;

/**
 * Alta y edicion de una sede. RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * Valida, invoca la Action y devuelve (regla 1). Aqui no hay ninguna decision
 * de negocio: ni siquiera compone los atributos del modelo, que es cosa del
 * DTO.
 *
 * Un solo componente para crear y editar porque el formulario es el mismo. Lo
 * que cambia es de donde salen los valores y que Action se invoca.
 */
final class SiteForm extends Component
{
    public ?Site $site = null;

    public string $code = '';

    public string $name = '';

    public string $timezone = 'America/Lima';

    public ?string $zoneId = null;

    public string $address = '';

    public string $latitude = '';

    public string $longitude = '';

    public string $opensAt = '';

    public string $closesAt = '';

    public string $activeFrom = '';

    public string $activeUntil = '';

    public function mount(?Site $site = null): void
    {
        // El binding de ruta entrega un modelo vacio cuando la ruta no lleva
        // parametro, de ahi la comprobacion por clave y no por null.
        if ($site instanceof Site && $site->exists) {
            $this->authorize('update', $site);

            $this->site = $site;
            $this->code = $site->code;
            $this->name = $site->name;
            $this->timezone = $site->timezone;
            $this->zoneId = $site->zone_id === null ? null : (string) $site->zone_id;
            $this->address = (string) $site->address;
            $this->latitude = (string) $site->latitude;
            $this->longitude = (string) $site->longitude;
            $this->opensAt = $this->asTime($site->opens_at);
            $this->closesAt = $this->asTime($site->closes_at);
            $this->activeFrom = $site->active_from?->toDateString() ?? '';
            $this->activeUntil = $site->active_until?->toDateString() ?? '';

            return;
        }

        $this->authorize('create', Site::class);
    }

    public function save(): void
    {
        $this->site instanceof Site
            ? $this->authorize('update', $this->site)
            : $this->authorize('create', Site::class);

        $this->validate();

        $data = new SiteData(
            code: $this->code,
            name: $this->name,
            timezone: $this->timezone,
            zoneId: $this->zoneId === null || $this->zoneId === '' ? null : (int) $this->zoneId,
            address: $this->blankToNull($this->address),
            latitude: $this->blankToNull($this->latitude),
            longitude: $this->blankToNull($this->longitude),
            opensAt: $this->blankToNull($this->opensAt),
            closesAt: $this->blankToNull($this->closesAt),
            activeFrom: $this->blankToNull($this->activeFrom),
            activeUntil: $this->blankToNull($this->activeUntil),
        );

        $this->site instanceof Site
            ? resolve(UpdateSite::class)($this->site, $data)
            : resolve(CreateSite::class)($data);

        session()->flash('status', $this->site instanceof Site
            ? __('Site updated.')
            : __('Site created.'));

        $this->redirectRoute('sites.index', navigate: true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:50',
                // Se consulta la tabla, no el modelo: asi la unicidad tiene en
                // cuenta tambien las sedes con borrado logico. Un codigo de
                // sede no se recicla; el indice de la base tampoco lo permite.
                Rule::unique('sites', 'code')->ignore($this->site?->getKey()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'timezone'],
            'zoneId' => ['nullable', Rule::exists('zones', 'id')->whereNull('deleted_at')],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'opensAt' => ['nullable', 'date_format:H:i'],
            // No se exige que el cierre sea posterior a la apertura: hay sedes
            // que cruzan la medianoche.
            'closesAt' => ['nullable', 'date_format:H:i'],
            'activeFrom' => ['nullable', 'date'],
            'activeUntil' => ['nullable', 'date', 'after_or_equal:activeFrom'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'code' => __('code'),
            'name' => __('name'),
            'timezone' => __('time zone'),
            'zoneId' => __('zone'),
            'address' => __('address'),
            'latitude' => __('latitude'),
            'longitude' => __('longitude'),
            'opensAt' => __('opening time'),
            'closesAt' => __('closing time'),
            'activeFrom' => __('start date'),
            'activeUntil' => __('end date'),
        ];
    }

    public function render(): View
    {
        return view('directory::sites.form', [
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name']),
            'timezones' => DateTimeZone::listIdentifiers(DateTimeZone::AMERICA),
            'editing' => $this->site instanceof Site,
        ]);
    }

    private function blankToNull(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }

    private function asTime(?string $value): string
    {
        // Postgres devuelve `08:00:00`; el input type=time espera `08:00`.
        return $value === null ? '' : mb_substr($value, 0, 5);
    }
}
