<?php

namespace Database\Factories;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'for_registration' => true,
            'name' => $this->faker->unique()->domainName(),
        ];
    }

    public function noRegistration(): static
    {
        return $this->state(fn (array $attributes) => [
            'for_registration' => false,
        ]);
    }
}
