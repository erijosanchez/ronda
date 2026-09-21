<?php

declare(strict_types=1);

namespace Ronda\Platform\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Ronda\Identity\Domain\Models\User;
use Ronda\Notifications\Application\Actions\SendNotification;
use Ronda\Notifications\Application\Data\NotificationMessage;
use Ronda\Notifications\Application\Queries\NotificationRecipientsQuery;
use Ronda\Notifications\Domain\NotificationTopic;
use Ronda\Platform\Domain\Exceptions\CannotImpersonate;
use Ronda\Platform\Domain\Models\ImpersonationEntry;
use Ronda\Platform\Domain\Models\PlatformUser;
use Ronda\Platform\Domain\Models\Tenant;
use Stancl\Tenancy\Database\Models\ImpersonationToken;

/**
 * Deja entrar a soporte en la cuenta de un cliente, con todo anotado.
 * RONDA-PLAN-MAESTRO.md sec. 15.4
 *
 * Las cuatro condiciones del plan, y ninguna es opcional:
 *
 *   1. MOTIVO obligatorio, y con un minimo de longitud: «revisar» no explica
 *      nada, y este texto lo va a leer el cliente.
 *   2. LIMITE de tiempo: la sesion caduca sola, aunque nadie pulse salir.
 *   3. REGISTRO en `impersonation_log`, que no se borra nunca.
 *   4. AVISO a quien manda en esa cuenta, en el momento, no despues.
 *
 * El orden importa: primero se anota y se avisa, y solo despues se entrega el
 * vale de entrada. Si el aviso fallara, nadie entra. Al reves —entrar y luego
 * intentar avisar— es como se acaba teniendo accesos que el cliente no vio.
 *
 * El vale es de un solo uso y dura segundos (lo gestiona stancl/tenancy): lo
 * que dura media hora es la sesion, no el enlace.
 */
final readonly class StartImpersonation
{
    public function __construct(
        private ConnectionInterface $connection,
        private SendNotification $notify,
        private NotificationRecipientsQuery $recipients,
    ) {}

    /**
     * @return string la direccion de un solo uso por la que se entra
     *
     * @throws CannotImpersonate
     */
    public function __invoke(
        PlatformUser $actor,
        Tenant $tenant,
        int $userId,
        string $reason,
        ?string $ip = null,
        ?string $userAgent = null,
        ?CarbonImmutable $now = null,
    ): string {
        $motivo = trim($reason);
        $minimo = (int) config('platform.impersonation.min_reason', 15);

        if (mb_strlen($motivo) < $minimo) {
            throw CannotImpersonate::reasonTooShort($minimo);
        }

        if (! $tenant->isReady()) {
            throw CannotImpersonate::tenantNotReady();
        }

        $ahora = $now ?? CarbonImmutable::now('UTC');
        $vence = $ahora->addMinutes((int) config('platform.impersonation.minutes', 30));

        // El correo se copia AHORA: el usuario vive en la base del cliente y
        // puede borrarse, y el registro tiene que seguir diciendo a quien se
        // suplanto.
        $correo = $tenant->run(static function () use ($userId): ?string {
            $usuario = User::query()->find($userId);

            return $usuario instanceof User ? (string) $usuario->email : null;
        });

        if ($correo === null) {
            throw CannotImpersonate::userNotFound();
        }

        $entrada = $this->connection->transaction(
            fn (): ImpersonationEntry => ImpersonationEntry::query()->create([
                'platform_user_id' => $actor->getKey(),
                'tenant_id' => $tenant->id,
                'impersonated_user_id' => $userId,
                'impersonated_user_email' => $correo,
                'reason' => $motivo,
                'started_at' => $ahora,
                'expires_at' => $vence,
                'ip_address' => $ip,
                'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 500),
            ]),
        );

        $this->warnTheClient($tenant, $actor, $entrada);

        // El vale se crea a mano y no con el macro `tenancy()->impersonate()`:
        // el macro solo existe si la «feature» del paquete esta activada, y
        // depender de eso hace que apagarla rompa la suplantacion sin que nada
        // lo avise. Esto es exactamente lo que hace el macro.
        $token = ImpersonationToken::query()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'user_id' => (string) $userId,
            'redirect_url' => '/panel',
            'auth_guard' => 'web',
        ]);

        $dominio = $tenant->domains()->value('domain');
        $esquema = str_starts_with((string) config('app.url'), 'https') ? 'https' : 'http';
        $puerto = $this->port();

        return "{$esquema}://{$dominio}{$puerto}/suplantacion/{$token->token}";
    }

    /**
     * Avisa a quien manda en la cuenta del cliente.
     *
     * Se resuelve preguntando a las Policies, una persona a la vez: no hay
     * comprobacion de roles fuera de una Policy (regla 4).
     */
    private function warnTheClient(Tenant $tenant, PlatformUser $actor, ImpersonationEntry $entry): void
    {
        $tenant->run(function () use ($actor, $entry): void {
            ($this->notify)(
                $this->recipients->administrators(),
                new NotificationMessage(
                    topic: NotificationTopic::ImpersonationStarted,
                    title: __('Ronda support entered your account'),
                    body: __(':who from Ronda support is going to look at your account until :until. Reason: :reason', [
                        'who' => $actor->name,
                        'until' => $entry->expires_at->timezone('America/Lima')->format('H:i'),
                        'reason' => $entry->reason,
                    ]),
                    url: '/panel',
                    meta: ['impersonation' => (string) $entry->getKey()],
                ),
            );
        });
    }

    /**
     * El puerto de `APP_URL`, si lo hay: en desarrollo todo se sirve en :8000 y
     * un enlace sin puerto no abre nada.
     */
    private function port(): string
    {
        $partes = parse_url((string) config('app.url'));

        return is_array($partes) && isset($partes['port']) && is_int($partes['port'])
            ? ':'.$partes['port']
            : '';
    }
}
