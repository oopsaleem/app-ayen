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

test('a category cannot be made its own parent via a string id', function () {
    $category = Category::factory()->create();

    $category->update(['parent_id' => (string) $category->id]);
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

test('reparenting a category updates descendant levels', function () {
    $restaurant = Restaurant::factory()->create();
    $topA = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => null]);
    $topB = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => null]);
    $child = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => $topA->id]);
    $grandchild = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => $child->id]);

    expect($grandchild->fresh()->level)->toBe(3);

    $child->update(['parent_id' => $topB->id]);

    expect($child->fresh()->level)->toBe(2)
        ->and($grandchild->fresh()->level)->toBe(3);

    $topBChild = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => $topB->id]);

    $child->update(['parent_id' => $topBChild->id]);

    expect($child->fresh()->level)->toBe(3)
        ->and($grandchild->fresh()->level)->toBe(4);
});

test('reverting a category to root updates descendant levels', function () {
    $restaurant = Restaurant::factory()->create();
    $top = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => null]);
    $child = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => $top->id]);
    $grandchild = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => $child->id]);

    $child->update(['parent_id' => null]);

    expect($child->fresh()->level)->toBe(1)
        ->and($grandchild->fresh()->level)->toBe(2);
});

test('a category cannot be parented under a category from a different restaurant', function () {
    $restaurantA = Restaurant::factory()->create();
    $restaurantB = Restaurant::factory()->create();
    $C1 = Category::factory()->create(['restaurant_id' => $restaurantA->id]);
    $C2 = Category::factory()->create(['restaurant_id' => $restaurantB->id]);

    $C2->update(['parent_id' => $C1->id]);
})->throws(InvalidArgumentException::class);
