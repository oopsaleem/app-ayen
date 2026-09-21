<?php

use App\Models\Admin;
use App\Models\Restaurant;
use App\Models\RestaurantVerification;

test('a restaurant verification defaults to unverified', function () {
    $restaurant = Restaurant::factory()->create();
    $verification = RestaurantVerification::factory()->create([
        'restaurant_id' => $restaurant->id,
        'verified' => false,
        'verified_by_admin_id' => null,
    ]);

    expect($verification->verified)->toBeFalse()
        ->and($restaurant->fresh()->verification->id)->toBe($verification->id);
});

test('a restaurant verification records which admin verified it', function () {
    $admin = Admin::factory()->create();
    $verification = RestaurantVerification::factory()->create([
        'verified_by_admin_id' => $admin->id,
        'verified' => true,
    ]);

    expect($verification->verifiedByAdmin->id)->toBe($admin->id)
        ->and($verification->verified)->toBeTrue();
});
