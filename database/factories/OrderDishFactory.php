<?php

namespace Database\Factories;

use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderDish;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderDish>
 */
class OrderDishFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 5, 50);

        return [
            'order_id' => Order::factory(),
            'dish_id' => Dish::factory(),
            'serving_size_id' => null,
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'total_price' => bcmul((string) $unitPrice, '1', 2),
            'status' => 'pending',
        ];
    }
}
