<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Realm>
 */
class RealmFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'uid' => $this->faker->randomLetter() . $this->faker->randomLetter(),
            'long_name' => $this->faker->company()
        ];
    }
}
