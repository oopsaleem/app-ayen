<?php

use App\Enums\OrderDishStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderDish;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Waiter;

/**
 * Create a waiter scoped to the given restaurant and log in as them.
 */
function waiterAt(Restaurant $restaurant): User
{
    $user = User::factory()->create();
    Waiter::factory()->for($user, 'user')->for($restaurant, 'restaurant')->create();

    return $user;
}

/**
 * Create a pickup order for the restaurant in the given status, with a real
 * transition history behind it.
 */
function pickupOrderIn(Restaurant $restaurant, OrderStatus $status): Order
{
    $order = Order::factory()
        ->for(User::factory(), 'user')
        ->for($restaurant)
        ->create(['delivery_mode' => 'pickup']);

    OrderDish::factory()->for($order, 'order')->create([
        'status' => OrderDishStatus::Ready->value,
    ]);

    $order->transitionTo(OrderStatus::Confirmed);
    $order->transitionTo(OrderStatus::Preparing);

    if ($status !== OrderStatus::Preparing) {
        $order->transitionTo(OrderStatus::AwaitingPickup);
    }

    return $order;
}

test('a waiter sees the restaurant s actionable pickup orders', function () {
    $restaurant = Restaurant::factory()->create();
    $waiter = waiterAt($restaurant);
    $order = pickupOrderIn($restaurant, OrderStatus::AwaitingPickup);

    $this->actingAs($waiter)->get(route('waiter.orders.index'))
        ->assertInertia(fn ($page) => $page
            ->component('waiter/orders')
            ->has('orders', 1)
            ->where('orders.0.id', $order->id)
            ->where('orders.0.status', OrderStatus::AwaitingPickup->value));
});

test('the waiter page does not show other statuses or other restaurants', function () {
    $myRestaurant = Restaurant::factory()->create();
    $otherRestaurant = Restaurant::factory()->create();
    $waiter = waiterAt($myRestaurant);

    pickupOrderIn($otherRestaurant, OrderStatus::AwaitingPickup);
    $stillPreparing = pickupOrderIn($myRestaurant, OrderStatus::Preparing);

    $this->actingAs($waiter)->get(route('waiter.orders.index'))
        ->assertInertia(fn ($page) => $page
            ->component('waiter/orders')
            ->has('orders', 0));

    expect($stillPreparing->fresh()->status)->toBe(OrderStatus::Preparing);
});

test('a waiter can hand off an awaiting pickup order', function () {
    $restaurant = Restaurant::factory()->create();
    $waiter = waiterAt($restaurant);

    $this->travelTo(now()->startOfMinute());
    $order = pickupOrderIn($restaurant, OrderStatus::AwaitingPickup);
    $this->travelBack();

    $this->travelTo($order->updated_at->copy()->addSeconds(45));
    $this->actingAs($waiter)
        ->post(route('waiter.orders.handOff', ['order' => $order->id]))
        ->assertRedirect();
    $this->travelBack();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Closed);

    $history = $order->statusHistory()->where('status', 'closed')->first();
    expect($history)->not->toBeNull()
        ->and($history->previous_status)->toBe(OrderStatus::AwaitingPickup)
        ->and($history->duration_in_previous_status)->toBe(45);
});

test('a waiter cannot hand off an order that is not awaiting pickup', function () {
    $restaurant = Restaurant::factory()->create();
    $waiter = waiterAt($restaurant);
    $order = pickupOrderIn($restaurant, OrderStatus::Preparing);

    $this->actingAs($waiter)
        ->post(route('waiter.orders.handOff', ['order' => $order->id]))
        ->assertStatus(422);

    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);
});

test('a waiter from another restaurant cannot hand off the order', function () {
    $restaurant = Restaurant::factory()->create();
    $order = pickupOrderIn($restaurant, OrderStatus::AwaitingPickup);
    $otherWaiter = waiterAt(Restaurant::factory()->create());

    $this->actingAs($otherWaiter)
        ->post(route('waiter.orders.handOff', ['order' => $order->id]))
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPickup)
        ->and($order->statusHistory()->where('status', 'closed')->count())->toBe(0);
});

test('hand off is first to act wins with no assignment record', function () {
    $restaurant = Restaurant::factory()->create();
    $waiterA = waiterAt($restaurant);
    $waiterB = waiterAt($restaurant);
    $order = pickupOrderIn($restaurant, OrderStatus::AwaitingPickup);

    $this->actingAs($waiterA)
        ->post(route('waiter.orders.handOff', ['order' => $order->id]))
        ->assertRedirect();

    $this->actingAs($waiterB)
        ->post(route('waiter.orders.handOff', ['order' => $order->id]))
        ->assertStatus(422)
        ->assertSessionHasNoErrors();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Closed)
        ->and($order->statusHistory()->where('status', 'closed')->count())->toBe(1);
});

test('users without the waiter role are forbidden', function () {
    $restaurant = Restaurant::factory()->create();
    $order = pickupOrderIn($restaurant, OrderStatus::AwaitingPickup);

    $this->actingAs(User::factory()->create())
        ->post(route('waiter.orders.handOff', ['order' => $order->id]))
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPickup);
});

test('a guest is redirected to login', function () {
    $restaurant = Restaurant::factory()->create();
    $order = pickupOrderIn($restaurant, OrderStatus::AwaitingPickup);

    $this->post(route('waiter.orders.handOff', ['order' => $order->id]))
        ->assertRedirect(route('login'));
});

test('an unknown order is not found', function () {
    $restaurant = Restaurant::factory()->create();
    $waiter = waiterAt($restaurant);

    $this->actingAs($waiter)
        ->post(route('waiter.orders.handOff', ['order' => 999999]))
        ->assertNotFound();
});
