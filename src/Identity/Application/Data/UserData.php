<?php

declare(strict_types=1);

namespace Ronda\Identity\Application\Data;

use Ronda\Identity\Domain\RoleName;
use Ronda\Identity\Domain\UserStatus;

/**
 * Datos de una persona del cliente, ya validados.
 * RONDA-PLAN-MAESTRO.md sec. 8.3
 *
 * La contrasena es opcional: al editar, dejarla vacia significa «no la toques».
 * Viaja en claro dentro del proceso y la hashea el cast `hashed` del modelo.
 *
 * @param  list<RoleName>  $roles
 */
final readonly class UserData
{
    /**
     * @param  list<RoleName>  $roles
     */
    public function __construct(
        public string $name,
        public string $email,
        public UserStatus $status,
        public array $roles,
        public ?string $phone = null,
        public ?string $password = null,
    ) {}

    /**
     * Atributos tal como los espera el modelo. La contrasena se omite cuando no
     * se ha indicado, para no pisar la que ya tiene.
     *
     * @return array<string, string|null>
     */
    public function toAttributes(): array
    {
        $attributes = [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status->value,
        ];

        if ($this->password !== null) {
            $attributes['password'] = $this->password;
        }

        return $attributes;
    }

    /**
     * @return list<string>
     */
    public function roleNames(): array
    {
        return array_map(
            static fn (RoleName $role): string => $role->value,
            $this->roles,
        );
    }
}
