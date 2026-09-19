<?php

declare(strict_types=1);

namespace Ronda\Forms\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Ronda\Forms\Application\Actions\CreateTemplate;
use Ronda\Forms\Application\Actions\PublishTemplateVersion;
use Ronda\Forms\Application\Data\TemplateData;
use Ronda\Forms\Domain\Exceptions\InvalidFormSchema;
use Ronda\Forms\Domain\Models\Template;
use Ronda\Forms\Domain\ValueObjects\FieldType;
use Ronda\Forms\Domain\ValueObjects\FormSchema;
use Ronda\Identity\Domain\Models\User;
use Ronda\Platform\Domain\Exceptions\PlanLimitExceeded;

/**
 * Disenador de plantillas. RONDA-PLAN-MAESTRO.md sec. 9.2
 *
 * Visual, no un editor de JSON: los campos se anaden, se ordenan y se
 * configuran con controles, y el esquema se arma al publicar.
 *
 * **No hay borradores.** El plan es explicito: guardar publica una version
 * nueva y las anteriores quedan intactas. El estado intermedio vive aqui, en el
 * componente, mientras se edita; en la base solo entran versiones publicadas
 * (ADR 0012).
 *
 * Al abrir una plantilla ya publicada se parte de su version vigente: se edita
 * sobre lo que hay y publicar crea la siguiente.
 */
final class TemplateDesigner extends Component
{
    public ?Template $template = null;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    /**
     * Campos en construccion, en el orden en que se mostraran.
     *
     * Array plano y no value objects: es el estado de un formulario que viaja
     * al navegador y vuelve. Se convierte en FormSchema al publicar, que es
     * donde se valida.
     *
     * @var list<array<string, mixed>>
     */
    public array $fields = [];

    public function mount(?Template $template = null): void
    {
        if ($template instanceof Template && $template->exists) {
            $this->authorize('update', $template);

            $this->template = $template;
            $this->code = $template->code;
            $this->name = $template->name;
            $this->description = (string) $template->description;

            // Se parte de la version vigente: editar es continuar desde lo
            // ultimo publicado, no empezar en blanco.
            $version = $template->currentVersion;

            if ($version !== null) {
                $this->fields = array_map(
                    $this->toFormState(...),
                    array_values($version->schema),
                );
            }

            return;
        }

        $this->authorize('create', Template::class);
        $this->addField();
    }

    public function addField(): void
    {
        $this->fields[] = [
            'key' => '',
            'type' => FieldType::Text->value,
            'label' => '',
            'help' => '',
            'required' => false,
            'reportable' => false,
            'options' => '',
        ];
    }

    public function removeField(int $index): void
    {
        // Se construye una lista nueva en vez de hacer `unset` sobre la que
        // hay: quitar una posicion intermedia deja un hueco, y entonces
        // Livewire manda al navegador un objeto en lugar de una lista y los
        // indices dejan de casar con lo que se ve en pantalla.
        $this->fields = array_values(array_filter(
            $this->fields,
            static fn (int $posicion): bool => $posicion !== $index,
            ARRAY_FILTER_USE_KEY,
        ));
    }

    public function moveUp(int $index): void
    {
        if ($index < 1 || ! isset($this->fields[$index])) {
            return;
        }

        [$this->fields[$index - 1], $this->fields[$index]] = [$this->fields[$index], $this->fields[$index - 1]];
    }

    public function moveDown(int $index): void
    {
        if (! isset($this->fields[$index + 1])) {
            return;
        }

        [$this->fields[$index], $this->fields[$index + 1]] = [$this->fields[$index + 1], $this->fields[$index]];
    }

    public function publish(): void
    {
        $template = $this->template;

        // Se comprueba publicar ANTES de tocar nada. Cuando la plantilla es
        // nueva se pregunta por la clase: la habilidad es del actor, no del
        // objeto, y crear primero dejaba una plantilla huerfana sin versiones
        // si luego faltaba el permiso.
        $this->authorize('publish', $template ?? Template::class);

        if (! $template instanceof Template) {
            $this->authorize('create', Template::class);
        }

        $this->validate();

        // El dominio es quien decide si el esquema se sostiene: PostgreSQL no
        // valida el contenido de un JSONB (ADR 0012). Su excepcion se traduce a
        // un error de formulario en vez de reventar la pantalla.
        try {
            $schema = FormSchema::fromArray($this->toSchemaArray());
        } catch (InvalidFormSchema $e) {
            $this->addError('fields', $e->getMessage());

            return;
        }

        if (! $template instanceof Template) {
            try {
                $template = resolve(CreateTemplate::class)(new TemplateData(
                    code: $this->code,
                    name: $this->name,
                    description: $this->description === '' ? null : $this->description,
                ));
            } catch (PlanLimitExceeded $e) {
                // Se avisa sobre el codigo, que es el primer campo: el error
                // no es del esquema que acaba de disenar.
                $this->addError('code', __('Your plan allows :limit templates. Retire one or move up a plan.', [
                    'limit' => $e->limitValue,
                ]));

                return;
            }
        } else {
            $template->update([
                'name' => $this->name,
                'description' => $this->description === '' ? null : $this->description,
            ]);
        }

        /** @var User $publisher */
        $publisher = auth()->user();

        resolve(PublishTemplateVersion::class)($template, $schema, $publisher);

        session()->flash('status', __('Template published.'));
        $this->redirectRoute('templates.index', navigate: true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('templates', 'code')->ignore($this->template?->getKey()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.key' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
            'fields.*.type' => ['required', Rule::enum(FieldType::class)],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.help' => ['nullable', 'string', 'max:500'],
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
            'fields' => __('fields'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fields.*.key.regex' => __('A field key uses lowercase letters, digits and underscores, and starts with a letter.'),
        ];
    }

    public function render(): View
    {
        return view('forms::templates.designer', [
            'types' => FieldType::cases(),
            'editing' => $this->template instanceof Template,
        ]);
    }

    /**
     * Del estado del formulario al array que entiende el dominio.
     *
     * @return list<array<string, mixed>>
     */
    private function toSchemaArray(): array
    {
        return array_map(static function (array $field): array {
            $options = array_values(array_filter(array_map(
                trim(...),
                explode("\n", (string) ($field['options'] ?? '')),
            ), static fn (string $option): bool => $option !== ''));

            return array_filter([
                'key' => trim((string) $field['key']),
                'type' => $field['type'],
                'label' => $field['label'],
                'help' => trim((string) ($field['help'] ?? '')) ?: null,
                'required' => (bool) ($field['required'] ?? false),
                'reportable' => (bool) ($field['reportable'] ?? false),
                'options' => $options,
            ], static fn (mixed $value): bool => $value !== null && $value !== []);
        }, array_values($this->fields));
    }

    /**
     * Del array guardado al estado del formulario.
     *
     * Las opciones se editan como texto, una por linea: es lo que menos estorba
     * para una lista corta y evita otro nivel de repetidor anidado.
     *
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function toFormState(array $field): array
    {
        /** @var list<string> $options */
        $options = is_array($field['options'] ?? null) ? $field['options'] : [];

        return [
            'key' => (string) ($field['key'] ?? ''),
            'type' => (string) ($field['type'] ?? FieldType::Text->value),
            'label' => (string) ($field['label'] ?? ''),
            'help' => (string) ($field['help'] ?? ''),
            'required' => (bool) ($field['required'] ?? false),
            'reportable' => (bool) ($field['reportable'] ?? false),
            'options' => implode("\n", $options),
        ];
    }
}
