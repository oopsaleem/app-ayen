<?php

namespace Database\Factories;

use App\Models\Kitchen;
use App\Models\Order;
use App\Models\OrderKitchen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderKitchen>
 */
class OrderKitchenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'kitchen_id' => Kitchen::factory(),
            'accepted_at' => null,
            'accepted_by' => null,
        ];
    }
}
