<?php

declare(strict_types=1);

namespace Ronda\Platform\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Ronda\Platform\Application\Actions\RegisterTenant;
use Ronda\Platform\Application\Data\CreateTenantData;
use Ronda\Platform\Domain\Exceptions\SubdomainRejection;
use Ronda\Platform\Domain\Exceptions\SubdomainUnavailable;

/**
 * Alta de un cliente nuevo desde la calle. RONDA-PLAN-MAESTRO.md sec. 15.3
 *
 * Valida, invoca la Action y devuelve (regla 1). Que `www` no sea de nadie y
 * que dos clientes no compartan direccion lo decide RegisterTenant; aqui solo
 * se muestra lo que responde.
 *
 * El limite por IP se comprueba en el componente y no con el middleware
 * `throttle` de la ruta porque el envio no pasa por la ruta: Livewire lo manda
 * a su propio endpoint. Un limite puesto solo en /registro se saltaria pidiendo
 * la pagina una vez y enviando el formulario mil.
 */
#[Layout('components.layouts.guest')]
final class RegisterForm extends Component
{
    public string $company = '';

    public string $subdomain = '';

    public string $ownerName = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $terms = false;

    /**
     * Mientras nadie toque el subdominio, se sugiere a partir del nombre. En
     * cuanto lo editan, deja de moverse solo: ver como cambia lo que uno acaba
     * de escribir es desconcertante.
     */
    public bool $subdomainEdited = false;

    public function mount(): void
    {
        abort_unless((bool) config('platform.registration.enabled', true), 404);
    }

    public function updatedCompany(string $value): void
    {
        if ($this->subdomainEdited) {
            return;
        }

        $this->subdomain = Str::slug($value);
    }

    public function updatedSubdomain(string $value): void
    {
        $this->subdomainEdited = true;
        $this->subdomain = Str::slug($value);
    }

    public function register(RegisterTenant $registerTenant): void
    {
        abort_unless((bool) config('platform.registration.enabled', true), 404);

        $llave = 'tenant-registration:'.request()->ip();
        $porHora = (int) config('platform.registration.per_hour', 5);

        if (RateLimiter::tooManyAttempts($llave, $porHora)) {
            $this->addError('company', __('Too many accounts created from here. Try again in :minutes minutes.', [
                'minutes' => (int) ceil(RateLimiter::availableIn($llave) / 60),
            ]));

            return;
        }

        $this->validate();

        try {
            $tenant = $registerTenant(new CreateTenantData(
                name: trim($this->company),
                slug: $this->subdomain,
                domain: $this->subdomain.'.'.config('platform.domain'),
                ownerName: trim($this->ownerName),
                ownerEmail: mb_strtolower(trim($this->email)),
                ownerPassword: $this->password,
            ));
        } catch (SubdomainUnavailable $e) {
            $this->addError('subdomain', $e->reason === SubdomainRejection::Reserved
                ? __('«:subdomain» is reserved. Choose another name.', ['subdomain' => $e->subdomain])
                : __('«:subdomain» is already taken. Choose another name.', ['subdomain' => $e->subdomain]));

            return;
        }

        // Se cuenta despues del alta: un subdominio ocupado no gasta intento.
        RateLimiter::hit($llave, 3600);

        // La contrasena no se queda en el componente ni un instante mas: entre
        // el alta y la redireccion hay un viaje de ida y vuelta al navegador.
        $this->reset('password', 'password_confirmation');

        $this->redirectRoute('register.waiting', ['tenant' => $tenant->id], navigate: false);
    }

    public function render(): View
    {
        return view('platform::register', [
            'dominio' => (string) config('platform.domain'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'company' => ['required', 'string', 'min:2', 'max:120'],
            // El subdominio acaba siendo una etiqueta de DNS: minusculas,
            // numeros y guiones, nunca al principio ni al final.
            'subdomain' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
            'ownerName' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:180'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'company' => __('company name'),
            'subdomain' => __('address'),
            'ownerName' => __('your name'),
            'email' => __('email'),
            'password' => __('password'),
            'terms' => __('terms'),
        ];
    }
}
