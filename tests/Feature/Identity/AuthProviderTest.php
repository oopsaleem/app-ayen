<?php

use App\Enums\AuthProviderType;
use App\Models\AuthProvider;
use App\Models\User;

test('a user has an auth provider recording how they signed up', function () {
    $user = User::factory()->create();
    $provider = AuthProvider::factory()->create([
        'user_id' => $user->id,
        'type' => AuthProviderType::Credentials,
    ]);

    expect($user->fresh()->authProvider->type)->toBe(AuthProviderType::Credentials)
        ->and($provider->user->id)->toBe($user->id);
});
