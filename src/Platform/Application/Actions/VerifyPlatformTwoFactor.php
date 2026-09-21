<?php

declare(strict_types=1);

namespace Ronda\Platform\Application\Actions;

use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Ronda\Platform\Domain\Exceptions\InvalidTwoFactorCode;
use Ronda\Platform\Domain\Models\PlatformUser;

/**
 * Comprueba el segundo factor al entrar al back-office.
 * RONDA-PLAN-MAESTRO.md sec. 15.4
 *
 * Acepta el codigo de la aplicacion o uno de recuperacion. El de recuperacion
 * se CONSUME al usarlo: sirve una vez, que es lo que lo hace un plan B y no una
 * segunda contrasena permanente.
 *
 * @throws InvalidTwoFactorCode
 */
final readonly class VerifyPlatformTwoFactor
{
    public function __construct(
        private TwoFactorAuthenticationProvider $totp,
    ) {}

    public function __invoke(PlatformUser $user, string $code): void
    {
        $codigo = trim($code);
        $secreto = $user->two_factor_secret;

        if ($secreto !== null && $this->totp->verify($secreto, $codigo)) {
            return;
        }

        $recuperacion = $this->recoveryCodes($user);

        if (! in_array($codigo, $recuperacion, true)) {
            throw InvalidTwoFactorCode::forChallenge();
        }

        $user->forceFill([
            'two_factor_recovery_codes' => json_encode(
                array_values(array_filter(
                    $recuperacion,
                    static fn (string $guardado): bool => $guardado !== $codigo,
                )),
                JSON_THROW_ON_ERROR,
            ),
        ])->save();
    }

    /**
     * @return list<string>
     */
    private function recoveryCodes(PlatformUser $user): array
    {
        $guardados = $user->two_factor_recovery_codes;

        if (! is_string($guardados)) {
            return [];
        }

        $codigos = json_decode($guardados, true);

        return is_array($codigos) ? array_values(array_filter($codigos, is_string(...))) : [];
    }
}
