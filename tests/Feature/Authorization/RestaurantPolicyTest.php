<?php

use App\Models\Admin;
use App\Models\Company;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\User;

test('any authenticated user can view any restaurant', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create();

    expect($user->can('view', $restaurant))->toBeTrue();
});

test('a manager can update only restaurants owned by their company', function () {
    $ownCompany = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    $user = User::factory()->create();
    Manager::factory()->create(['user_id' => $user->id, 'company_id' => $ownCompany->id]);

    $ownRestaurant = Restaurant::factory()->create(['company_id' => $ownCompany->id]);
    $otherRestaurant = Restaurant::factory()->create(['company_id' => $otherCompany->id]);

    expect($user->can('update', $ownRestaurant))->toBeTrue()
        ->and($user->can('update', $otherRestaurant))->toBeFalse();
});

test('an admin can update any restaurant', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    $restaurant = Restaurant::factory()->create();

    expect($admin->can('update', $restaurant))->toBeTrue();
});

test('only an admin can verify a restaurant', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id]);

    $restaurant = Restaurant::factory()->create();

    expect($admin->can('verify', $restaurant))->toBeTrue()
        ->and($manager->can('verify', $restaurant))->toBeFalse();
});
