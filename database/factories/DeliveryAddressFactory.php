<?php

namespace Database\Factories;

use App\Models\DeliveryAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryAddress>
 */
class DeliveryAddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'caption' => fake()->randomElement(['Home', 'Work']),
            'address' => fake()->address(),
            'lat' => fake()->latitude(),
            'lng' => fake()->longitude(),
            'is_default' => false,
        ];
    }
}
