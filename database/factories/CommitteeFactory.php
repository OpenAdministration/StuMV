<?php

namespace Database\Factories;

use App\Models\Committee;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommitteeFactory extends Factory
{
    protected $model = Committee::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
        ];
    }
}
