<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'sku' => strtoupper(fake()->unique()->bothify('???-###')),
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
            'description' => fake()->optional()->paragraph(),
            'current_stock' => fake()->numberBetween(0, 200),
        ];
    }

    public function lowStock(): static
    {
        return $this->state(fn () => ['current_stock' => fake()->numberBetween(0, 9)]);
    }
}
