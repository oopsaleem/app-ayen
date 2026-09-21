<?php

use App\Models\Admin;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\RestaurantVerification;
use App\Models\User;

test('an admin can verify a restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $response = $this->actingAs($admin)->post(route('restaurants.verify', $restaurant));

    $response->assertRedirect(route('restaurants.edit', $restaurant));
    $this->assertDatabaseHas('restaurant_verifications', [
        'restaurant_id' => $restaurant->id,
        'verified' => true,
    ]);
});

test('an admin can unverify a previously verified restaurant', function () {
    $restaurant = Restaurant::factory()
        ->has(RestaurantVerification::factory()->state(['verified' => true]), 'verification')
        ->create();
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->post(route('restaurants.verify', $restaurant));

    expect($restaurant->verification()->first()->verified)->toBeFalse();
});

test('a manager cannot verify a restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $this->actingAs($manager)->post(route('restaurants.verify', $restaurant))->assertForbidden();
});
