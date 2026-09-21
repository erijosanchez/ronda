<?php

declare(strict_types=1);

namespace Ronda\Platform\Presentation\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Ronda\Platform\Application\Actions\EnrollPlatformTwoFactor;
use Ronda\Platform\Application\Actions\VerifyPlatformTwoFactor;
use Ronda\Platform\Domain\Exceptions\InvalidTwoFactorCode;
use Ronda\Platform\Domain\Models\PlatformUser;

/**
 * Entrada al back-office de Ronda. RONDA-PLAN-MAESTRO.md sec. 15.4
 *
 * Tres pasos en una sola pantalla: credenciales, y despues el segundo factor
 * —configurarlo si no lo tiene, o pedirlo si ya lo tiene—. El 2FA no es
 * opcional: esta cuenta puede entrar a la operacion de cualquier cliente.
 *
 * La sesion NO se abre hasta que el segundo factor pasa. Entre medias, quien
 * esta a medio entrar vive en la sesion del servidor y no en el componente: lo
 * que viaja al navegador se puede mirar, y ahi no va a estar el id de nadie.
 *
 * El limite de intentos combina correo e IP, igual que el login de los
 * clientes: limitar solo por IP deja pasar el rociado desde una botnet, y solo
 * por cuenta permite enumerar desde una sola IP.
 */
#[Layout('components.layouts.guest')]
final class BackOfficeLogin extends Component
{
    public string $email = '';

    public string $password = '';

    public string $code = '';

    /** `credenciales` | `configurar` | `codigo` | `respaldo` */
    public string $step = 'credenciales';

    public string $qr = '';

    public string $secret = '';

    /** @var list<string> */
    public array $recoveryCodes = [];

    public function submit(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $llave = 'back-office:'.mb_strtolower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($llave, 5)) {
            $this->addError('email', __('Too many attempts. Try again in :seconds seconds.', [
                'seconds' => RateLimiter::availableIn($llave),
            ]));

            return;
        }

        $usuario = PlatformUser::query()->where('email', mb_strtolower(trim($this->email)))->first();

        // El mismo mensaje exista o no la cuenta: decir cual de las dos cosas
        // fallo es regalar la mitad del trabajo a quien prueba.
        if (! $usuario instanceof PlatformUser || ! $usuario->is_active || ! Hash::check($this->password, $usuario->password)) {
            RateLimiter::hit($llave, 900);
            $this->addError('email', __('Those credentials do not match.'));

            return;
        }

        RateLimiter::clear($llave);
        session()->put('back-office.pending', $usuario->getKey());
        $this->reset('password');

        if ($usuario->hasTwoFactor()) {
            $this->step = 'codigo';

            return;
        }

        $alta = resolve(EnrollPlatformTwoFactor::class)->start($usuario);
        $this->qr = $alta['qr'];
        $this->secret = $alta['secret'];
        $this->step = 'configurar';
    }

    public function confirmEnrollment(): void
    {
        $usuario = $this->pendingUser();

        if (! $usuario instanceof PlatformUser) {
            return;
        }

        $this->validate(['code' => ['required', 'string']]);

        try {
            $this->recoveryCodes = resolve(EnrollPlatformTwoFactor::class)->confirm($usuario, $this->code);
        } catch (InvalidTwoFactorCode) {
            $this->addError('code', __('The code is not valid.'));

            return;
        }

        // Los codigos de recuperacion se ensenan UNA vez: quedan cifrados en la
        // base y no hay pantalla para volver a verlos.
        $this->step = 'respaldo';
        $this->reset('code');
    }

    public function challenge(): void
    {
        $usuario = $this->pendingUser();

        if (! $usuario instanceof PlatformUser) {
            return;
        }

        $this->validate(['code' => ['required', 'string']]);

        $llave = 'back-office-2fa:'.$usuario->getKey().'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($llave, 5)) {
            $this->addError('code', __('Too many attempts. Try again in :seconds seconds.', [
                'seconds' => RateLimiter::availableIn($llave),
            ]));

            return;
        }

        try {
            resolve(VerifyPlatformTwoFactor::class)($usuario, $this->code);
        } catch (InvalidTwoFactorCode) {
            RateLimiter::hit($llave, 900);
            $this->addError('code', __('The code is not valid.'));

            return;
        }

        RateLimiter::clear($llave);
        $this->signIn($usuario);
    }

    public function finishEnrollment(): void
    {
        $usuario = $this->pendingUser();

        if ($usuario instanceof PlatformUser) {
            $this->signIn($usuario);
        }
    }

    public function render(): View
    {
        return view('platform::back-office.login');
    }

    private function signIn(PlatformUser $user): void
    {
        $user->forceFill(['last_login_at' => CarbonImmutable::now('UTC')])->save();

        Auth::guard('platform')->login($user);

        // Sesion nueva despues de autenticar: sin esto, un identificador de
        // sesion obtenido antes seguiria sirviendo despues.
        session()->forget('back-office.pending');
        session()->regenerate();

        $this->redirectRoute('back-office.tenants', navigate: false);
    }

    private function pendingUser(): ?PlatformUser
    {
        $id = session('back-office.pending');

        return is_int($id) || is_string($id)
            ? PlatformUser::query()->find($id)
            : null;
    }
}
