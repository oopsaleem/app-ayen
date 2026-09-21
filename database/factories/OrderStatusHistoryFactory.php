<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\OrderStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderStatusHistory>
 */
class OrderStatusHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => OrderFactory::new(),
            'status' => OrderStatus::Confirmed,
            'previous_status' => OrderStatus::Pending,
            'duration_in_previous_status' => $this->faker->numberBetween(30, 3600),
        ];
    }
}
