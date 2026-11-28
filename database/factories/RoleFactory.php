<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
        ];
    }

    /** @return Factory<Role> */
    public function default_names(): Factory
    {
        return $this->sequence(
            ['name' => 'Active'],
            ['name' => 'Elected'],
            ['name' => 'Member'],
            ['name' => 'Leader'],
            ['name' => 'Deputy Leader'],
        );
    }
}
