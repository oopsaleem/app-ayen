<?php

use App\Models\Admin;
use App\Models\User;

test('the auth shared prop includes the users roles', function () {
    $user = User::factory()->create();
    Admin::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/');

    $response->assertInertia(fn ($page) => $page
        ->where('auth.roles', ['admin']));
});

test('a user with no role rows shares an empty roles array', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertInertia(fn ($page) => $page
        ->where('auth.roles', []));
});
