<?php

namespace Database\Factories;

use App\Models\Dish;
use App\Models\DishOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DishOption>
 */
class DishOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dish_id' => Dish::factory(),
            'name_en' => fake()->randomElement(['Extra cheese', 'Spicy', 'No onions', 'Extra sauce']),
            'name_ar' => fake()->randomElement(['جبنة اضافية', 'حار', 'بدون بصل', 'صلصة اضافية']),
            'price' => fake()->randomFloat(2, 0.5, 10),
            'is_available' => true,
        ];
    }
}
