<?php

namespace Database\Factories;

use App\Models\Dish;
use App\Models\ServingSize;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServingSize>
 */
class ServingSizeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dish_id' => Dish::factory(),
            'name_en' => fake()->randomElement(['Small', 'Medium', 'Large']),
            'name_ar' => fake()->randomElement(['صغير', 'وسط', 'كبير']),
            'price' => fake()->randomFloat(2, 5, 50),
            'is_default' => false,
            'servings_count' => 1,
        ];
    }
}
