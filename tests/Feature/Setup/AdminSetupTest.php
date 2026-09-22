<?php

use App\Models\Admin;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('setup page is accessible to guests when no admin exists', function () {
    $this->get(route('setup.admin.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('setup/admin'));
});

test('a guest can create the first admin', function () {
    $response = $this->post(route('setup.admin.store'), [
        'name' => 'First Admin',
        'email' => 'first-admin@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'first-admin@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->fresh()->isAdmin())->toBeTrue();

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('companies.index'));
});

test('setup page redirects guests to login once an admin exists', function () {
    Admin::factory()->create();

    $this->get(route('setup.admin.create'))
        ->assertRedirect(route('login'));
});

test('setup page is forbidden to a logged-in non-admin once an admin exists', function () {
    Admin::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('setup.admin.create'))
        ->assertForbidden();
});

test('setup page requires password confirmation for an existing admin', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->get(route('setup.admin.create'))
        ->assertRedirect(route('password.confirm'));
});

test('an existing admin can create another admin without losing their own session', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $response = $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('setup.admin.store'), [
            'name' => 'Second Admin',
            'email' => 'second-admin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $newUser = User::where('email', 'second-admin@example.com')->first();

    expect($newUser->fresh()->isAdmin())->toBeTrue();

    $this->assertAuthenticatedAs($admin);
    $response->assertRedirect(route('companies.index'));
});
