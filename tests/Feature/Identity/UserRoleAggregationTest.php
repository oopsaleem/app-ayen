<?php

use App\Models\Admin;
use App\Models\Chef;
use App\Models\Manager;
use App\Models\User;

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
