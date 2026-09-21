<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantAddress>
 */
class RestaurantAddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'address' => fake()->address(),
            'lat' => fake()->latitude(),
            'lng' => fake()->longitude(),
        ];
    }
}
