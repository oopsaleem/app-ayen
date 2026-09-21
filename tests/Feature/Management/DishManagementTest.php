<?php

use App\Models\Admin;
use App\Models\Category;
use App\Models\Chef;
use App\Models\Dish;
use App\Models\Kitchen;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\User;

test('the dish index lists a restaurants dishes', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->create(['restaurant_id' => $restaurant->id]);
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    Dish::factory()->count(2)->create(['kitchen_id' => $kitchen->id, 'category_id' => $category->id]);
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->get(route('dishes.index', $restaurant))
        ->assertInertia(fn ($page) => $page->component('menu/dishes/index')->has('dishes', 2));
});

test('a manager from another company cannot view a restaurants dishes', function () {
    $restaurant = Restaurant::factory()->create();
    $otherManager = User::factory()->create();
    Manager::factory()->create(['user_id' => $otherManager->id]);

    $this->actingAs($otherManager)->get(route('dishes.index', $restaurant))
        ->assertForbidden();
});

test('a chef cannot view the dish creation form', function () {
    $restaurant = Restaurant::factory()->create();
    $chef = User::factory()->create();
    Chef::factory()->create(['user_id' => $chef->id]);

    $this->actingAs($chef)->get(route('dishes.create', $restaurant))
        ->assertForbidden();
});

test('a manager can create a dish in their restaurants kitchen', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->create(['restaurant_id' => $restaurant->id]);
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $response = $this->actingAs($manager)->post(route('dishes.store', $restaurant), [
        'name_en' => 'Mandi',
        'name_ar' => 'مندي',
        'price' => 25.5,
        'kitchen_id' => $kitchen->id,
        'category_id' => $category->id,
    ]);

    $dish = Dish::firstWhere('name_en', 'Mandi');
    $response->assertRedirect(route('dishes.index', $restaurant));
    $this->assertDatabaseHas('dishes', ['name_en' => 'Mandi', 'kitchen_id' => $kitchen->id]);
});

test('a manager cannot assign a dish to a kitchen from another restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $otherKitchen = Kitchen::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $this->actingAs($manager)->post(route('dishes.store', $restaurant), [
        'name_en' => 'Invalid',
        'name_ar' => 'غير صالح',
        'price' => 10,
        'kitchen_id' => $otherKitchen->id,
        'category_id' => $category->id,
    ])->assertInvalid(['kitchen_id']);
});
