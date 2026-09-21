<?php

use App\Models\Category;
use App\Models\Restaurant;

test('a top-level category has level 1', function () {
    $category = Category::factory()->create(['parent_id' => null]);

    expect($category->level)->toBe(1);
});

test('a subcategory level is computed as parent level plus one', function () {
    $restaurant = Restaurant::factory()->create();
    $parent = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => null]);
    $child = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => $parent->id]);

    expect($child->fresh()->level)->toBe(2);

    $grandchild = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => $child->id]);

    expect($grandchild->fresh()->level)->toBe(3);
});

test('level is recomputed when a category is reparented', function () {
    $restaurant = Restaurant::factory()->create();
    $topA = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => null]);
    $topB = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => null]);
    $child = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => $topA->id]);

    expect($child->fresh()->level)->toBe(2);

    $grandchild = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => $child->id]);
    expect($grandchild->fresh()->level)->toBe(3);

    $child->update(['parent_id' => $topB->id]);

    expect($child->fresh()->level)->toBe(2);
});

test('a category cannot be made its own parent', function () {
    $category = Category::factory()->create();

    $category->update(['parent_id' => $category->id]);
})->throws(InvalidArgumentException::class);

test('a category cannot be reparented under its own descendant', function () {
    $restaurant = Restaurant::factory()->create();
    $top = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => null]);
    $child = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => $top->id]);

    $top->update(['parent_id' => $child->id]);
})->throws(InvalidArgumentException::class);

test('a category belongs to exactly one restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);

    expect($category->restaurant->id)->toBe($restaurant->id);
});
