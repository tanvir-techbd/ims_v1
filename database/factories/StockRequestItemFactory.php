<?php

namespace Database\Factories;

use App\Enums\RequestItemStatus;
use App\Models\Product;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockRequestItem>
 */
class StockRequestItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_request_id' => StockRequest::factory(),
            'product_id' => Product::factory(),
            'requested_qty' => fake()->numberBetween(1, 20),
            'approved_qty' => null,
            'issued_qty' => 0,
            'status' => RequestItemStatus::Pending,
        ];
    }
}
