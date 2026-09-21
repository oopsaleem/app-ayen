<?php

use App\Models\Admin;
use App\Models\Category;
use App\Models\Chef;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\User;

test('the category index lists a restaurants categories', function () {
    $restaurant = Restaurant::factory()->create();
    Category::factory()->count(2)->create(['restaurant_id' => $restaurant->id]);
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->get(route('categories.index', $restaurant))
        ->assertInertia(fn ($page) => $page->component('menu/categories/index')->has('categories', 2));
});

test('a manager from another company cannot view a restaurants categories', function () {
    $restaurant = Restaurant::factory()->create();
    Category::factory()->count(2)->create(['restaurant_id' => $restaurant->id]);
    $otherManager = User::factory()->create();
    Manager::factory()->create(['user_id' => $otherManager->id]);

    $this->actingAs($otherManager)->get(route('categories.index', $restaurant))
        ->assertForbidden();
});

test('a chef cannot view a restaurants categories', function () {
    $restaurant = Restaurant::factory()->create();
    $chef = User::factory()->create();
    Chef::factory()->create(['user_id' => $chef->id]);

    $this->actingAs($chef)->get(route('categories.index', $restaurant))
        ->assertForbidden();
});

test('a manager can create a top level category', function () {
    $restaurant = Restaurant::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $response = $this->actingAs($manager)->post(route('categories.store', $restaurant), [
        'name_en' => 'Grills',
        'name_ar' => 'مشاوي',
        'parent_id' => null,
    ]);

    $category = Category::firstWhere('name_en', 'Grills');
    $response->assertRedirect(route('categories.index', $restaurant));
    $this->assertDatabaseHas('categories', ['restaurant_id' => $restaurant->id, 'name_en' => 'Grills']);
});

test('a manager can create a child category under a parent in the same restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $parent = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $this->actingAs($manager)->post(route('categories.store', $restaurant), [
        'name_en' => 'Chicken grills',
        'name_ar' => 'مشاوي دجاج',
        'parent_id' => $parent->id,
    ])->assertRedirect(route('categories.index', $restaurant));

    $child = Category::firstWhere('name_en', 'Chicken grills');
    expect($child->parent_id)->toBe($parent->id);
});

test('a manager cannot create a category under another restaurants parent', function () {
    $restaurant = Restaurant::factory()->create();
    $otherRestaurantParent = Category::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $this->actingAs($manager)->post(route('categories.store', $restaurant), [
        'name_en' => 'Invalid',
        'name_ar' => 'غير صالح',
        'parent_id' => $otherRestaurantParent->id,
    ])->assertInvalid(['parent_id']);
});

test('a manager cannot move a category under its own descendant', function () {
    $restaurant = Restaurant::factory()->create();
    $parent = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $child = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => $parent->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $this->actingAs($manager)->patch(route('categories.update', $parent), [
        'name_en' => $parent->name_en,
        'name_ar' => $parent->name_ar,
        'parent_id' => $child->id,
    ])->assertInvalid(['parent_id']);

    expect($parent->refresh()->parent_id)->toBeNull();
});
