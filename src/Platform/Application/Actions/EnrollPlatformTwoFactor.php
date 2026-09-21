<?php

declare(strict_types=1);

namespace Ronda\Platform\Application\Actions;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\CarbonImmutable;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\RecoveryCode;
use Ronda\Platform\Domain\Exceptions\InvalidTwoFactorCode;
use Ronda\Platform\Domain\Models\PlatformUser;

/**
 * Da de alta el segundo factor de alguien del equipo de Ronda.
 * RONDA-PLAN-MAESTRO.md sec. 15.4
 *
 * Dos pasos, y el segundo es el que importa:
 *
 *   1. `start()` genera el secreto y lo guarda SIN confirmar.
 *   2. `confirm()` exige un codigo valido antes de darlo por bueno.
 *
 * Confirmar con un codigo y no solo con «ya lo guarde» evita el caso peor: una
 * cuenta que cree tener 2FA porque vio el QR, con una aplicacion que nunca
 * llego a guardarlo, y que queda fuera de su propio back-office.
 *
 * Reutiliza el proveedor TOTP de Fortify: el algoritmo es el mismo que ya usan
 * los usuarios de cada cliente, y no hay motivo para tener dos.
 */
final readonly class EnrollPlatformTwoFactor
{
    public function __construct(
        private TwoFactorAuthenticationProvider $totp,
    ) {}

    /**
     * Genera el secreto y devuelve el QR ya dibujado.
     *
     * El QR se entrega como imagen en base64 y no como HTML: en Blade esta
     * prohibido `{!! !!}` (CLAUDE.md), y con razon —es por donde entra el XSS
     * cuando alguien mete ahi algo que no controla—. Una imagen en un atributo
     * la escapa Blade sin que haya que pensarlo.
     *
     * @return array{secret: string, qr: string}
     */
    public function start(PlatformUser $user): array
    {
        $secreto = $this->totp->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secreto,
            // Nulo a proposito: hasta que no se confirme con un codigo, esta
            // cuenta sigue sin poder entrar.
            'two_factor_confirmed_at' => null,
        ])->save();

        $url = $this->totp->qrCodeUrl(
            config('app.name').' · Soporte',
            $user->email,
            $secreto,
        );

        return [
            'secret' => $secreto,
            'qr' => 'data:image/svg+xml;base64,'.base64_encode($this->svg($url)),
        ];
    }

    /**
     * @return list<string> los codigos de recuperacion, que se muestran UNA vez
     *
     * @throws InvalidTwoFactorCode
     */
    public function confirm(PlatformUser $user, string $code): array
    {
        $secreto = $user->two_factor_secret;

        if ($secreto === null || ! $this->totp->verify($secreto, $code)) {
            throw InvalidTwoFactorCode::forEnrollment();
        }

        $codigos = array_map(
            RecoveryCode::generate(...),
            range(1, 8),
        );

        $user->forceFill([
            'two_factor_recovery_codes' => json_encode(array_values($codigos), JSON_THROW_ON_ERROR),
            'two_factor_confirmed_at' => CarbonImmutable::now('UTC'),
        ])->save();

        return array_values($codigos);
    }

    private function svg(string $url): string
    {
        return new Writer(
            new ImageRenderer(
                new RendererStyle(192, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(24, 24, 27))),
                new SvgImageBackEnd,
            ),
        )->writeString($url);
    }
}
