<?php

declare(strict_types=1);

namespace Ronda\Directory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ronda\Directory\Domain\Models\Site;

/**
 * @extends Factory<Site>
 */
final class SiteFactory extends Factory
{
    /** @var class-string<Site> */
    protected $model = Site::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => mb_strtoupper(fake()->unique()->bothify('S-###')),
            'name' => 'Sede '.fake()->unique()->city(),
            'address' => fake()->streetAddress(),
            'latitude' => fake()->latitude(-18, 0),      // Peru, grosso modo
            'longitude' => fake()->longitude(-81, -68),
            'timezone' => 'America/Lima',
            'opens_at' => '08:00:00',
            'closes_at' => '20:00:00',
        ];
    }

    /**
     * Sede ya cerrada: sirve para comprobar que no aparece en lo operativo.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'active_until' => now()->subDay()->toDateString(),
        ]);
    }
}
