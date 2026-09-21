<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name_en' => fake()->company(),
            'name_ar' => 'مطعم '.fake()->word(),
            'description_en' => fake()->sentence(),
            'description_ar' => fake()->sentence(),
            'images' => [],
        ];
    }
}
