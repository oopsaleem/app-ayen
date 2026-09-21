<?php

use App\Models\Category;
use App\Models\Dish;
use App\Models\DishOption;
use App\Models\Kitchen;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\User;

test('a manager can add an option to their dish', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->create(['restaurant_id' => $restaurant->id]);
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $dish = Dish::factory()->create(['kitchen_id' => $kitchen->id, 'category_id' => $category->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $response = $this->actingAs($manager)->post(route('dish-options.store', $dish), [
        'name_en' => 'Extra cheese',
        'name_ar' => 'جبنة إضافية',
        'price' => 2.5,
    ]);

    $response->assertRedirect(route('dishes.edit', $dish));
    $this->assertDatabaseHas('dish_options', ['dish_id' => $dish->id, 'name_en' => 'Extra cheese']);
});

test('a manager can update and delete an option on their dish', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->create(['restaurant_id' => $restaurant->id]);
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $dish = Dish::factory()->create(['kitchen_id' => $kitchen->id, 'category_id' => $category->id]);
    $option = DishOption::factory()->create(['dish_id' => $dish->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $this->actingAs($manager)->patch(route('dish-options.update', $option), [
        'name_en' => 'Renamed',
        'name_ar' => $option->name_ar,
        'price' => 3,
    ])->assertRedirect(route('dishes.edit', $dish));

    expect($option->fresh()->name_en)->toBe('Renamed');

    $this->actingAs($manager)->delete(route('dish-options.destroy', $option))
        ->assertRedirect(route('dishes.edit', $dish));

    $this->assertDatabaseMissing('dish_options', ['id' => $option->id]);
});

test('a manager from another company cannot manage an option', function () {
    $dish = Dish::factory()->create();
    $option = DishOption::factory()->create(['dish_id' => $dish->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id]);

    $this->actingAs($manager)->patch(route('dish-options.update', $option), [
        'name_en' => 'Renamed', 'name_ar' => 'x', 'price' => 1,
    ])->assertForbidden();
});
