<?php

declare(strict_types=1);

namespace Ronda\Forms\Application\Actions;

use Ronda\Forms\Application\Data\TemplateData;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Forms\Domain\TemplateStatus;
use Ronda\Platform\Domain\Contracts\PlanProvider;
use Ronda\Platform\Domain\Exceptions\PlanLimitExceeded;

/**
 * Crea una plantilla vacia. RONDA-PLAN-MAESTRO.md sec. 9.2
 *
 * Nace en borrador y sin ninguna version: el contenido llega con
 * PublishTemplateVersion. Escribe una sola tabla, asi que no abre transaccion
 * (la regla 3 la exige para escrituras multi-tabla).
 *
 * El limite del plan se comprueba AQUI y no en la pantalla: por aqui pasan
 * tanto el disenador como la instalacion del catalogo, y manana la API. Una
 * comprobacion en el formulario dejaria abiertas las otras dos puertas.
 *
 * Se pregunta por el contrato del dominio de Platform y no por su capa de
 * aplicacion: la regla de dependencia no deja que un modulo llame a la
 * aplicacion de otro, y ademas asi la prueba sustituye el plan sin base.
 */
final readonly class CreateTemplate
{
    public function __construct(
        private PlanProvider $plan,
    ) {}

    /**
     * @throws PlanLimitExceeded si el plan contratado no da para una mas
     */
    public function __invoke(TemplateData $data): Template
    {
        $limits = $this->plan->limits();

        // Las dadas de baja no cuentan: el limite es cuantas plantillas tiene
        // en uso el cliente, no cuantas creo alguna vez.
        if (! $limits->allowsAnotherTemplate(Template::query()->count())) {
            throw PlanLimitExceeded::templates((int) $limits->maxTemplates);
        }

        return Template::create([
            ...$data->toAttributes(),
            'status' => TemplateStatus::Draft->value,
        ]);
    }
}
