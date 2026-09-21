<?php

use App\Models\Chef;
use App\Models\Manager;
use App\Models\User;

test('a user becomes a chef by having a chef row', function () {
    $user = User::factory()->create();

    expect($user->isChef())->toBeFalse();

    Chef::factory()->create(['user_id' => $user->id]);

    expect($user->fresh()->isChef())->toBeTrue();
});

test('a chef belongs to its user', function () {
    $user = User::factory()->create();
    $chef = Chef::factory()->create(['user_id' => $user->id]);

    expect($chef->user->id)->toBe($user->id);
});

test('a single user can hold more than one role at once', function () {
    $user = User::factory()->create();

    Chef::factory()->create(['user_id' => $user->id]);
    Manager::factory()->create(['user_id' => $user->id]);

    $fresh = $user->fresh();

    expect($fresh->isChef())->toBeTrue()
        ->and($fresh->isManager())->toBeTrue();
});
