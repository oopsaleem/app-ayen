<?php

use App\Models\Restaurant;
use App\Models\RestaurantAddress;
use Illuminate\Database\QueryException;

test('a restaurant has exactly one address', function () {
    $restaurant = Restaurant::factory()->create();
    $address = RestaurantAddress::factory()->create(['restaurant_id' => $restaurant->id]);

    expect($restaurant->fresh()->address->id)->toBe($address->id)
        ->and($address->restaurant->id)->toBe($restaurant->id);
});

test('a restaurant address stores coordinates', function () {
    $address = RestaurantAddress::factory()->create([
        'address' => '123 Main St, Sana\'a',
        'lat' => 15.3547,
        'lng' => 44.2066,
    ]);

    expect((float) $address->lat)->toBe(15.3547)
        ->and((float) $address->lng)->toBe(44.2066);
});

test('a restaurant cannot have two addresses', function () {
    $restaurant = Restaurant::factory()->create();
    RestaurantAddress::factory()->create(['restaurant_id' => $restaurant->id]);

    RestaurantAddress::factory()->create(['restaurant_id' => $restaurant->id]);
})->throws(QueryException::class);
