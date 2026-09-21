<?php

namespace Database\Factories;

use App\Enums\DeliveryMode;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'restaurant_id' => Restaurant::factory(),
            'delivery_mode' => DeliveryMode::Delivery,
            'delivery_address_id' => null,
            'status' => OrderStatus::Pending,
            'subtotal' => '0.00',
            'vat' => '0.00',
            'delivery_fee' => '0.00',
            'total' => '0.00',
        ];
    }
}
