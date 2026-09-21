<?php

use App\Models\Manager;
use App\Models\Rider;
use App\Models\User;
use App\Models\Waiter;

test('a user becomes a rider by having a rider row', function () {
    $user = User::factory()->create();

    expect($user->isRider())->toBeFalse();

    Rider::factory()->create(['user_id' => $user->id]);

    expect($user->fresh()->isRider())->toBeTrue();
});

test('a user becomes a waiter by having a waiter row', function () {
    $user = User::factory()->create();

    expect($user->isWaiter())->toBeFalse();

    Waiter::factory()->create(['user_id' => $user->id]);

    expect($user->fresh()->isWaiter())->toBeTrue();
});

test('a rider belongs to its user and restaurant', function () {
    $rider = Rider::factory()->create();

    expect($rider->user->id)->toBe($rider->user_id)
        ->and($rider->restaurant->id)->toBe($rider->restaurant_id);
});

test('a waiter belongs to its user and restaurant', function () {
    $waiter = Waiter::factory()->create();

    expect($waiter->user->id)->toBe($waiter->user_id)
        ->and($waiter->restaurant->id)->toBe($waiter->restaurant_id);
});

test('a user can hold at most one rider row per user', function () {
    $user = User::factory()->create();
    Rider::factory()->create(['user_id' => $user->id]);

    expect(fn () => Rider::factory()->create(['user_id' => $user->id]))
        ->toThrow(Exception::class);
});

test('a user can hold at most one waiter row per user', function () {
    $user = User::factory()->create();
    Waiter::factory()->create(['user_id' => $user->id]);

    expect(fn () => Waiter::factory()->create(['user_id' => $user->id]))
        ->toThrow(Exception::class);
});

test('a rider and a waiter row are tied to one restaurant only', function () {
    $user = User::factory()->create();
    $rider = Rider::factory()->create(['user_id' => $user->id]);
    $waiter = Waiter::factory()->create(['user_id' => $user->id]);

    expect($rider->restaurant->id)->toBe($rider->restaurant_id)
        ->and($waiter->restaurant->id)->toBe($waiter->restaurant_id);
});

test('roles are additive across all role tables', function () {
    $user = User::factory()->create();

    Rider::factory()->create(['user_id' => $user->id]);
    Waiter::factory()->create(['user_id' => $user->id]);
    Manager::factory()->create(['user_id' => $user->id]);

    $fresh = $user->fresh();

    expect($fresh->isRider())->toBeTrue()
        ->and($fresh->isWaiter())->toBeTrue()
        ->and($fresh->isManager())->toBeTrue()
        ->and($fresh->roles())->toBe(['manager', 'rider', 'waiter']);
});
