<?php

use App\Models\Admin;
use App\Models\User;

test('a user becomes an admin by having an admin row', function () {
    $user = User::factory()->create();

    expect($user->isAdmin())->toBeFalse();

    Admin::factory()->create(['user_id' => $user->id]);

    expect($user->fresh()->isAdmin())->toBeTrue();
});

test('an admin belongs to its user', function () {
    $user = User::factory()->create();
    $admin = Admin::factory()->create(['user_id' => $user->id]);

    expect($admin->user->id)->toBe($user->id);
});
