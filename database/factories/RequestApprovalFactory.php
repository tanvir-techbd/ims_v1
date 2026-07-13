<?php

namespace Database\Factories;

use App\Enums\ApprovalDecision;
use App\Models\RequestApproval;
use App\Models\StockRequestItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestApproval>
 */
class RequestApprovalFactory extends Factory
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
            'approver_id' => User::factory(),
            'decision' => ApprovalDecision::Approved,
            'approved_qty' => fake()->numberBetween(1, 10),
            'remarks' => fake()->optional()->sentence(),
        ];
    }
}
