<?php

use App\Models\Category;
use App\Models\Dish;
use App\Models\Kitchen;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\ServingSize;
use App\Models\User;

test('a manager can add a serving size and mark it default', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->create(['restaurant_id' => $restaurant->id]);
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $dish = Dish::factory()->create(['kitchen_id' => $kitchen->id, 'category_id' => $category->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $this->actingAs($manager)->post(route('serving-sizes.store', $dish), [
        'name_en' => 'Small', 'name_ar' => 'صغير', 'price' => 10, 'is_default' => true,
    ])->assertRedirect(route('dishes.edit', $dish));

    $this->assertDatabaseHas('serving_sizes', ['dish_id' => $dish->id, 'name_en' => 'Small', 'is_default' => true]);
});

test('marking a new serving size as default unsets the previous default', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->create(['restaurant_id' => $restaurant->id]);
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $dish = Dish::factory()->create(['kitchen_id' => $kitchen->id, 'category_id' => $category->id]);
    $existingDefault = ServingSize::factory()->create(['dish_id' => $dish->id, 'is_default' => true]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $this->actingAs($manager)->post(route('serving-sizes.store', $dish), [
        'name_en' => 'Large', 'name_ar' => 'كبير', 'price' => 20, 'is_default' => true,
    ]);

    expect($existingDefault->fresh()->is_default)->toBeFalse();
    $this->assertDatabaseHas('serving_sizes', ['dish_id' => $dish->id, 'name_en' => 'Large', 'is_default' => true]);
});
