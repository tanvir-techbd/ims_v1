<?php

namespace Database\Factories;

use App\Models\ItemGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemGroup>
 */
class ItemGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => str($name)->slug(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
