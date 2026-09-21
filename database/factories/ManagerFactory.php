<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Manager;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Manager>
 */
class ManagerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_id' => Company::factory(),
            'display_name' => fake()->name(),
        ];
    }
}
