<?php

use App\Models\Dish;
use App\Models\DishOption;

test('a dish option belongs to a dish', function () {
    $dish = Dish::factory()->create();
    $option = DishOption::factory()->create(['dish_id' => $dish->id]);

    expect($option->dish->id)->toBe($dish->id);
});

test('a dish option defaults to available', function () {
    $option = DishOption::factory()->create();

    expect($option->is_available)->toBeTrue();
});

test('a dish option can be disabled without being deleted', function () {
    $option = DishOption::factory()->create(['is_available' => true]);

    $option->update(['is_available' => false]);

    $this->assertDatabaseHas('dish_options', [
        'id' => $option->id,
        'is_available' => false,
    ]);
});
