<?php

use App\Models\Dish;
use App\Models\ServingSize;

test('a serving size belongs to a dish', function () {
    $dish = Dish::factory()->create();
    $servingSize = ServingSize::factory()->create(['dish_id' => $dish->id]);

    expect($servingSize->dish->id)->toBe($dish->id);
});

test('marking a serving size default unsets the previous default for that dish', function () {
    $dish = Dish::factory()->create();
    $small = ServingSize::factory()->create(['dish_id' => $dish->id, 'is_default' => true]);
    $large = ServingSize::factory()->create(['dish_id' => $dish->id, 'is_default' => false]);

    $large->update(['is_default' => true]);

    expect($small->fresh()->is_default)->toBeFalse()
        ->and($large->fresh()->is_default)->toBeTrue();
});

test('serving sizes on different dishes do not affect each other default flag', function () {
    $dishA = Dish::factory()->create();
    $dishB = Dish::factory()->create();

    $sizeA = ServingSize::factory()->create(['dish_id' => $dishA->id, 'is_default' => true]);
    $sizeB = ServingSize::factory()->create(['dish_id' => $dishB->id, 'is_default' => true]);

    expect($sizeA->fresh()->is_default)->toBeTrue()
        ->and($sizeB->fresh()->is_default)->toBeTrue();
});
