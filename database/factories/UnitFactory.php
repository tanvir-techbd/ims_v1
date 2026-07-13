<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Piece', 'Box', 'Ream', 'Bottle', 'Pack', 'Litre', 'Kilogram', 'Roll']),
            'symbol' => fn (array $attrs) => str($attrs['name'])->slug(),
        ];
    }
}
