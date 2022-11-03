<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Domain>
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

    public function noRegistration()
    {
        return $this->state(function (array $attributes) {
            return [
                'for_registration' => false,
            ];
        });
    }
}
