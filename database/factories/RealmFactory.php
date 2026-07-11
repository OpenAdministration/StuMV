<?php

namespace Database\Factories;

use App\Models\Realm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Realm>
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
            'uid' => $this->faker->randomLetter().$this->faker->randomLetter().$this->faker->randomLetter(),
            'long_name' => $this->faker->unique()->company(),
        ];
    }
}
