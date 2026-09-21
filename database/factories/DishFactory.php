<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Kitchen;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dish>
 */
class DishFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $restaurant = Restaurant::factory()->create();

        return [
            'category_id' => Category::factory()->for($restaurant, 'restaurant'),
            'kitchen_id' => Kitchen::factory()->for($restaurant, 'restaurant'),
            'name_en' => fake()->words(3, true),
            'name_ar' => 'طبق '.fake()->word(),
            'description_en' => fake()->sentence(),
            'description_ar' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 5, 50),
            'is_available' => true,
            'images' => [],
        ];
    }
}
