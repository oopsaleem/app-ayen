<?php

use App\Models\Admin;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Kitchen;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\User;

test('admins and the owning companys manager can manage dishes', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->create(['restaurant_id' => $restaurant->id]);
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $dish = Dish::factory()->create(['kitchen_id' => $kitchen->id, 'category_id' => $category->id]);
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);
    $otherManager = User::factory()->create();
    Manager::factory()->create(['user_id' => $otherManager->id]);

    expect($manager->can('create', [Dish::class, $kitchen]))->toBeTrue()
        ->and($manager->can('update', $dish))->toBeTrue()
        ->and($manager->can('delete', $dish))->toBeTrue()
        ->and($otherManager->can('update', $dish))->toBeFalse()
        ->and($admin->can('view', $dish))->toBeTrue();
});
