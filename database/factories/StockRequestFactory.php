<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\StockRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockRequest>
 */
class StockRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requester_id' => User::factory(),
            'status' => RequestStatus::Pending,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
