<?php

use App\Models\Admin;
use App\Models\Category;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\User;

test('admins and the owning companys manager can manage categories', function () {
    $restaurant = Restaurant::factory()->create();
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);
    $otherManager = User::factory()->create();
    Manager::factory()->create(['user_id' => $otherManager->id]);

    expect($manager->can('create', [Category::class, $restaurant]))->toBeTrue()
        ->and($manager->can('update', $category))->toBeTrue()
        ->and($manager->can('delete', $category))->toBeTrue()
        ->and($otherManager->can('update', $category))->toBeFalse()
        ->and($admin->can('view', $category))->toBeTrue();
});
