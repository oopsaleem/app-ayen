<?php

use App\Models\Restaurant;
use App\Models\Rider;
use App\Models\User;

test('a user becomes a rider by having a rider row', function () {
    $user = User::factory()->create();

    expect($user->isRider())->toBeFalse();

    Rider::factory()->create(['user_id' => $user->id]);

    expect($user->fresh()->isRider())->toBeTrue();
});

test('a rider belongs to its user and restaurant', function () {
    $rider = Rider::factory()->create();

    expect($rider->user->id)->toBe($rider->user_id)
        ->and($rider->restaurant->id)->toBe($rider->restaurant_id);
});

test('a user can hold at most one rider row per user', function () {
    $user = User::factory()->create();
    Rider::factory()->create(['user_id' => $user->id]);

    expect(fn () => Rider::factory()->create(['user_id' => $user->id]))
        ->toThrow(Exception::class);
});

test('a rider row requires a restaurant', function () {
    $user = User::factory()->create();

    expect(fn () => Rider::factory()->create(['user_id' => $user->id, 'restaurant_id' => null]))
        ->toThrow(Exception::class);
});

test('two riders can be scoped to the same restaurant open-pool', function () {
    $restaurant = Restaurant::factory()->create();

    $riderOne = Rider::factory()->create(['restaurant_id' => $restaurant->id]);
    $riderTwo = Rider::factory()->create(['restaurant_id' => $restaurant->id]);

    expect($riderOne->restaurant_id)->toBe($restaurant->id)
        ->and($riderTwo->restaurant_id)->toBe($restaurant->id)
        ->and($riderOne->id)->not->toBe($riderTwo->id);
});

test('riders on different restaurants are isolated', function () {
    $riderOne = Rider::factory()->create();
    $riderTwo = Rider::factory()->create();

    expect($riderOne->restaurant_id)->not->toBe($riderTwo->restaurant_id);
});
