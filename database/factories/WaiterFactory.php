<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\User;
use App\Models\Waiter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Waiter>
 */
class WaiterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'restaurant_id' => Restaurant::factory(),
        ];
    }
}
