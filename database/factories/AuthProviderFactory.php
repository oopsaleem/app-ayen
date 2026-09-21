<?php

namespace Database\Factories;

use App\Enums\AuthProviderType;
use App\Models\AuthProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuthProvider>
 */
class AuthProviderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => AuthProviderType::Credentials,
        ];
    }
}
