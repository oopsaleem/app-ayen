<?php

use App\Enums\OrderStatus;
use App\Enums\TeamRole;
use App\Models\Admin;
use App\Models\Chef;
use App\Models\Company;
use App\Models\Kitchen;
use App\Models\Manager;
use App\Models\Order;
use App\Models\OrderKitchen;
use App\Models\Restaurant;
use App\Models\RestaurantVerification;
use App\Models\Rider;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Models\Waiter;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
});

test('dashboard includes pending invitations for the authenticated user', function () {
    $owner = User::factory()->create(['name' => 'Taylor Otwell']);
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create(['name' => 'Laravel Team']);

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 1)
        ->where('pendingInvitations.0.code', $invitation->code)
        ->where('pendingInvitations.0.inviterName', 'Taylor Otwell')
        ->where('pendingInvitations.0.team.name', 'Laravel Team')
        ->where('pendingInvitations.0.team.slug', $team->slug)
        ->missing('pendingInvitations.0.teamName'),
    );
});

test('dashboard does not include accepted invitations', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    TeamInvitation::factory()->accepted()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 0),
    );
});

test('dashboard excludes expired invitations without deleting them', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->expired()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 0),
    );

    $this->assertDatabaseHas('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('dashboard does not include or delete other users invitations', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->expired()->create([
        'team_id' => $team->id,
        'email' => 'someone@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 0),
    );

    $this->assertDatabaseHas('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('an admin sees the platform summary on the dashboard', function () {
    $admin = Admin::factory()->create();
    $company = Company::factory()->create();

    $verified = Restaurant::factory()->for($company)->create();
    RestaurantVerification::factory()->for($verified, 'restaurant')->create(['verified' => true]);

    Restaurant::factory()->for($company)->create();

    $unverifiedWithRow = Restaurant::factory()->for($company)->create();
    RestaurantVerification::factory()->for($unverifiedWithRow, 'restaurant')->create(['verified' => false]);

    $response = $this->actingAs($admin->user)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('admin.companies', Company::count())
        ->where('admin.restaurants', Restaurant::count())
        ->where('admin.unverified_restaurants', 2)
        ->where('manager', null));
});

test('a manager sees only their own company s restaurant count', function () {
    $company = Company::factory()->create();
    $manager = Manager::factory()->for($company)->create();

    Restaurant::factory()->for($company)->count(2)->create();
    Restaurant::factory()->create();

    $response = $this->actingAs($manager->user)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('manager.restaurants', 2)
        ->where('admin', null));
});

test('a chef sees their kitchen and actionable order counts', function () {
    $chefUser = User::factory()->create();
    $chef = Chef::factory()->for($chefUser, 'user')->create();
    $kitchen = Kitchen::factory()->create();
    Kitchen::factory()->create();

    $chef->kitchens()->attach($kitchen->id, ['assigned_at' => now()]);

    $pending = Order::factory()->for($kitchen->restaurant)->create([
        'user_id' => $chefUser->id,
        'status' => OrderStatus::Pending,
    ]);
    OrderKitchen::factory()->for($pending, 'order')->for($kitchen, 'kitchen')->create();

    $closed = Order::factory()->for($kitchen->restaurant)->create([
        'user_id' => $chefUser->id,
        'status' => OrderStatus::Closed,
    ]);
    OrderKitchen::factory()->for($closed, 'order')->for($kitchen, 'kitchen')->create();

    $response = $this->actingAs($chefUser)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('chef.kitchens', 1)
        ->where('chef.actionable_orders', 1));
});

test('a waiter sees their restaurant s waiting pickup count', function () {
    $restaurant = Restaurant::factory()->create();
    $waiterUser = User::factory()->create();
    Waiter::factory()->for($waiterUser, 'user')->for($restaurant, 'restaurant')->create();

    Order::factory()->for($restaurant)->create([
        'user_id' => $waiterUser->id,
        'delivery_mode' => 'pickup',
        'status' => OrderStatus::AwaitingPickup,
    ]);
    Order::factory()->for($restaurant)->create([
        'user_id' => $waiterUser->id,
        'delivery_mode' => 'pickup',
        'status' => OrderStatus::Preparing,
    ]);

    $response = $this->actingAs($waiterUser)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('waiter.pickups', 1));
});

test('a rider sees their restaurant s waiting delivery count', function () {
    $restaurant = Restaurant::factory()->create();
    $riderUser = User::factory()->create();
    Rider::factory()->for($riderUser, 'user')->for($restaurant, 'restaurant')->create();

    Order::factory()->for($restaurant)->create([
        'user_id' => $riderUser->id,
        'delivery_mode' => 'delivery',
        'status' => OrderStatus::AwaitingDelivery,
    ]);
    Order::factory()->for($restaurant)->create([
        'user_id' => $riderUser->id,
        'delivery_mode' => 'delivery',
        'status' => OrderStatus::OutForDelivery,
    ]);
    Order::factory()->for($restaurant)->create([
        'user_id' => $riderUser->id,
        'delivery_mode' => 'delivery',
        'status' => OrderStatus::Closed,
    ]);

    $response = $this->actingAs($riderUser)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('rider.deliveries', 2));
});

test('any authenticated user sees their own active order count and no staff summaries', function () {
    Order::factory()->create();

    $user = User::factory()->create();
    Order::factory()->for($user)->create(['status' => OrderStatus::Pending]);
    Order::factory()->for($user)->create(['status' => OrderStatus::Closed]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('customer.active_orders', 1)
        ->where('admin', null)
        ->where('manager', null)
        ->where('chef', null)
        ->where('waiter', null)
        ->where('rider', null));
});
