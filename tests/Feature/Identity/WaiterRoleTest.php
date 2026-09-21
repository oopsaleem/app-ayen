<?php

use App\Models\Restaurant;
use App\Models\User;
use App\Models\Waiter;

test('a user becomes a waiter by having a waiter row', function () {
    $user = User::factory()->create();

    expect($user->isWaiter())->toBeFalse();

    Waiter::factory()->create(['user_id' => $user->id]);

    expect($user->fresh()->isWaiter())->toBeTrue();
});

test('a waiter belongs to its user and restaurant', function () {
    $waiter = Waiter::factory()->create();

    expect($waiter->user->id)->toBe($waiter->user_id)
        ->and($waiter->restaurant->id)->toBe($waiter->restaurant_id);
});

test('a user can hold at most one waiter row per user', function () {
    $user = User::factory()->create();
    Waiter::factory()->create(['user_id' => $user->id]);

    expect(fn () => Waiter::factory()->create(['user_id' => $user->id]))
        ->toThrow(Exception::class);
});

test('a waiter row requires a restaurant', function () {
    $user = User::factory()->create();

    expect(fn () => Waiter::factory()->create(['user_id' => $user->id, 'restaurant_id' => null]))
        ->toThrow(Exception::class);
});

test('two waiters can be scoped to the same restaurant open-pool', function () {
    $restaurant = Restaurant::factory()->create();

    $waiterOne = Waiter::factory()->create(['restaurant_id' => $restaurant->id]);
    $waiterTwo = Waiter::factory()->create(['restaurant_id' => $restaurant->id]);

    expect($waiterOne->restaurant_id)->toBe($restaurant->id)
        ->and($waiterTwo->restaurant_id)->toBe($restaurant->id)
        ->and($waiterOne->id)->not->toBe($waiterTwo->id);
});

test('waiters on different restaurants are isolated', function () {
    $waiterOne = Waiter::factory()->create();
    $waiterTwo = Waiter::factory()->create();

    expect($waiterOne->restaurant_id)->not->toBe($waiterTwo->restaurant_id);
});
