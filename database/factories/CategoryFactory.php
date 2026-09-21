<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name_en' => fake()->words(2, true),
            'name_ar' => 'فئة '.fake()->word(),
            'description_en' => fake()->sentence(),
            'description_ar' => fake()->sentence(),
            'image' => null,
            'is_active' => true,
            'parent_id' => null,
            'order' => 0,
        ];
    }
}
