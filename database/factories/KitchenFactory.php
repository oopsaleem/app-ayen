<?php

namespace Database\Factories;

use App\Models\Kitchen;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kitchen>
 */
class KitchenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name_en' => fake()->randomElement(['Grill', 'Bakery', 'Cold Kitchen', 'Dessert Station']),
            'name_ar' => fake()->randomElement(['شواية', 'مخبز', 'مطبخ بارد', 'محطة الحلويات']),
        ];
    }
}
