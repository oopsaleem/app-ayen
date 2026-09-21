<?php

use App\Models\Admin;
use App\Models\Chef;
use App\Models\Manager;
use App\Models\Rider;
use App\Models\User;
use App\Models\Waiter;

test('a user with no role rows has no roles', function () {
    $user = User::factory()->create();

    expect($user->roles())->toBe([]);
});

test('roles are aggregated across all role tables the user holds', function () {
    $user = User::factory()->create();

    Admin::factory()->create(['user_id' => $user->id]);
    Manager::factory()->create(['user_id' => $user->id]);
    Chef::factory()->create(['user_id' => $user->id]);

    expect($user->fresh()->roles())->toBe(['admin', 'manager', 'chef']);
});

test('roles are additive across all role tables including rider and waiter', function () {
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
