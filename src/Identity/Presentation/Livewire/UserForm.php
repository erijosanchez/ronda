<?php

declare(strict_types=1);

namespace Ronda\Identity\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Ronda\Identity\Application\Actions\CreateUser;
use Ronda\Identity\Application\Actions\UpdateUser;
use Ronda\Identity\Application\Data\UserData;
use Ronda\Identity\Domain\Models\User;
use Ronda\Identity\Domain\RoleName;
use Ronda\Identity\Domain\UserStatus;

/**
 * Alta y edicion de una persona del cliente.
 *
 * Valida, invoca la Action y devuelve (regla 1).
 */
final class UserForm extends Component
{
    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $status = 'active';

    public string $password = '';

    /** @var list<string> */
    public array $roles = [];

    public function mount(?User $user = null): void
    {
        if ($user instanceof User && $user->exists) {
            $this->authorize('update', $user);

            $this->user = $user;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->phone = (string) $user->phone;
            $this->status = $user->status->value;
            /** @var list<string> $roles */
            $roles = array_values($user->roles->pluck('name')->all());
            $this->roles = $roles;

            return;
        }

        $this->authorize('create', User::class);
    }

    public function save(): void
    {
        $this->user instanceof User
            ? $this->authorize('update', $this->user)
            : $this->authorize('create', User::class);

        $this->validate();

        $data = new UserData(
            name: $this->name,
            email: $this->email,
            status: UserStatus::from($this->status),
            roles: array_values(array_filter(array_map(
                RoleName::tryFrom(...),
                $this->roles,
            ))),
            phone: trim($this->phone) === '' ? null : trim($this->phone),
            // Vacia al editar significa «no la toques».
            password: $this->password === '' ? null : $this->password,
        );

        $this->user instanceof User
            ? resolve(UpdateUser::class)($this->user, $data)
            : resolve(CreateUser::class)($data);

        session()->flash('status', $this->user instanceof User
            ? __('User updated.')
            : __('User created.'));

        $this->redirectRoute('users.index', navigate: true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                // Sobre la tabla, no el modelo: cuenta tambien a los usuarios
                // dados de baja, cuyo correo sigue ocupado.
                Rule::unique('users', 'email')->ignore($this->user?->getKey()),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'roles' => ['array'],
            'roles.*' => [Rule::enum(RoleName::class)],
            // Al crear es obligatoria; al editar, vacia significa no cambiarla.
            // La politica (longitud minima y comprobacion contra filtraciones)
            // la fija FortifyServiceProvider con Password::defaults().
            'password' => [
                $this->user instanceof User ? 'nullable' : 'required',
                Password::defaults(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'name' => __('name'),
            'email' => __('email'),
            'phone' => __('phone'),
            'status' => __('status'),
            'roles' => __('roles'),
            'password' => __('password'),
        ];
    }

    public function render(): View
    {
        return view('identity::users.form', [
            'allRoles' => RoleName::cases(),
            'allStatuses' => UserStatus::cases(),
            'editing' => $this->user instanceof User,
        ]);
    }
}
