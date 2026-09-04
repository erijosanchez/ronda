<?php

declare(strict_types=1);

namespace Ronda\Identity\Presentation\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Fortify;

/**
 * Fortify es headless: registra las rutas de autenticacion pero no impone
 * vistas. Aqui se enlazan las de Ronda y se fija la politica de acceso.
 *
 * Ver RONDA-PLAN-MAESTRO.md sec. 10.2
 */
final class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerViews();
        $this->registerRateLimiters();
        $this->registerPasswordPolicy();
    }

    private function registerViews(): void
    {
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
    }

    /**
     * Bloqueo progresivo por cuenta Y por IP.
     *
     * Limitar solo por IP deja pasar el rociado de contrasenas desde una
     * botnet; limitar solo por cuenta permite enumerar usuarios desde una
     * sola IP. Se combinan las dos claves.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $email = (string) $request->input('email');

            return Limit::perMinute(5)->by(mb_strtolower($email).'|'.$request->ip());
        });

        RateLimiter::for('two-factor', fn (Request $request): Limit => Limit::perMinute(5)->by(
            (string) $request->session()->get('login.id'),
        ));
    }

    /**
     * Politica de contrasenas alineada con NIST 800-63B: longitud por encima
     * de complejidad arbitraria, mas comprobacion contra filtraciones
     * conocidas. Sin caducidad forzada, que solo produce contrasenas peores.
     */
    private function registerPasswordPolicy(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min((int) config('security.password.min_length', 12));

            return config('security.password.check_compromised', true)
                ? $rule->uncompromised()
                : $rule;
        });
    }
}
