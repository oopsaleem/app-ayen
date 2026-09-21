<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantVerification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantVerification>
 */
class RestaurantVerificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'verified_by_admin_id' => null,
            'verified' => false,
        ];
    }
}
