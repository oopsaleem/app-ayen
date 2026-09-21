<?php

use App\Models\Kitchen;
use App\Models\Restaurant;

test('a kitchen belongs to a restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->create(['restaurant_id' => $restaurant->id]);

    expect($kitchen->restaurant->id)->toBe($restaurant->id)
        ->and($restaurant->fresh()->kitchens)->toHaveCount(1);
});

test('a kitchen stores a bilingual name', function () {
    $kitchen = Kitchen::factory()->create([
        'name_en' => 'Grill',
        'name_ar' => 'شواية',
    ]);

    app()->setLocale('en');
    expect($kitchen->localized('name'))->toBe('Grill');

    app()->setLocale('ar');
    expect($kitchen->localized('name'))->toBe('شواية');
});
