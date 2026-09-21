<?php

use App\Models\Category;
use App\Models\Chef;
use App\Models\ChefKitchen;
use App\Models\Dish;
use App\Models\Kitchen;
use App\Models\User;

test('a chef sees only their assigned kitchens and dishes', function () {
    $chefUser = User::factory()->create();
    $chef = Chef::factory()->create(['user_id' => $chefUser->id]);
    $assignedKitchen = Kitchen::factory()->create();
    $otherKitchen = Kitchen::factory()->create();

    ChefKitchen::create(['chef_id' => $chef->id, 'kitchen_id' => $assignedKitchen->id, 'assigned_at' => now()]);
    Dish::factory()->create([
        'kitchen_id' => $assignedKitchen->id,
        'category_id' => Category::factory()->create(['restaurant_id' => $assignedKitchen->restaurant_id])->id,
    ]);
    Dish::factory()->create([
        'kitchen_id' => $otherKitchen->id,
        'category_id' => Category::factory()->create(['restaurant_id' => $otherKitchen->restaurant_id])->id,
    ]);

    $response = $this->actingAs($chefUser)->get(route('chef.kitchens.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('chef/kitchens')
        ->has('kitchens', 1)
        ->has('kitchens.0.dishes', 1));
});

test('a non chef cannot view the chef kitchen page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('chef.kitchens.index'))->assertForbidden();
});
