<?php

namespace Database\Factories;

use App\Models\StockIssuance;
use App\Models\StockRequestItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockIssuance>
 */
class StockIssuanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_request_item_id' => StockRequestItem::factory(),
            'storekeeper_id' => User::factory(),
            'issued_qty' => fake()->numberBetween(1, 10),
            'remarks' => fake()->optional()->sentence(),
        ];
    }
}
