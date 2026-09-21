<?php

use App\Models\Category;
use App\Models\Dish;
use App\Models\Kitchen;

test('a dish belongs to a category and a kitchen', function () {
    $category = Category::factory()->create();
    $kitchen = Kitchen::factory()->create();
    $dish = Dish::factory()->create([
        'category_id' => $category->id,
        'kitchen_id' => $kitchen->id,
    ]);

    expect($dish->category->id)->toBe($category->id)
        ->and($dish->kitchen->id)->toBe($kitchen->id);
});

test('a dish can be toggled unavailable without being deleted', function () {
    $dish = Dish::factory()->create(['is_available' => true]);

    $dish->update(['is_available' => false]);

    expect($dish->fresh())->not->toBeNull()
        ->and($dish->fresh()->is_available)->toBeFalse();
});

test('a dish stores a single image set, not light/dark variants', function () {
    $dish = Dish::factory()->create([
        'images' => ['https://example.test/dish.jpg'],
    ]);

    expect($dish->fresh()->images)->toBe(['https://example.test/dish.jpg']);
});
