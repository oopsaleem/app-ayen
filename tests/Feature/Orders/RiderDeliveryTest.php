<?php

use App\Enums\OrderDishStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderDish;
use App\Models\Restaurant;
use App\Models\Rider;
use App\Models\User;

/**
 * Create a rider scoped to the given restaurant and log in as them.
 */
function riderAt(Restaurant $restaurant): User
{
    $user = User::factory()->create();
    Rider::factory()->for($user, 'user')->for($restaurant, 'restaurant')->create();

    return $user;
}

/**
 * Create a delivery order for the restaurant in the given status, with
 * a real transition history behind it.
 */
function deliveryOrderIn(Restaurant $restaurant, OrderStatus $status): Order
{
    $order = Order::factory()
        ->for(User::factory(), 'user')
        ->for($restaurant)
        ->create(['delivery_mode' => 'delivery']);

    OrderDish::factory()->for($order, 'order')->create([
        'status' => OrderDishStatus::Ready->value,
    ]);

    $order->transitionTo(OrderStatus::Confirmed);
    $order->transitionTo(OrderStatus::Preparing);

    if ($status !== OrderStatus::Preparing) {
        $order->transitionTo(OrderStatus::AwaitingDelivery);
    }

    if ($status === OrderStatus::OutForDelivery) {
        $order->transitionTo(OrderStatus::OutForDelivery);
    }

    return $order;
}

test('a rider sees the restaurant s actionable delivery orders', function () {
    $restaurant = Restaurant::factory()->create();
    $rider = riderAt($restaurant);
    $this->travelTo(now()->startOfMinute());
    $awaiting = deliveryOrderIn($restaurant, OrderStatus::AwaitingDelivery);
    $this->travel(30)->seconds();
    $out = deliveryOrderIn($restaurant, OrderStatus::OutForDelivery);
    $this->travelBack();

    $this->actingAs($rider)->get(route('rider.orders.index'))
        ->assertInertia(fn ($page) => $page
            ->component('rider/orders')
            ->has('orders', 2)
            ->has('orders', fn ($orders) => $orders
                ->has(0, fn ($order) => $order
                    ->where('id', $out->id)
                    ->where('status', OrderStatus::OutForDelivery->value)
                    ->etc())
                ->has(1, fn ($order) => $order
                    ->where('id', $awaiting->id)
                    ->where('status', OrderStatus::AwaitingDelivery->value)
                    ->etc())));
});

test('the rider page does not show other statuses or other restaurants', function () {
    $myRestaurant = Restaurant::factory()->create();
    $otherRestaurant = Restaurant::factory()->create();
    $rider = riderAt($myRestaurant);

    deliveryOrderIn($otherRestaurant, OrderStatus::AwaitingDelivery);

    $stillPreparing = deliveryOrderIn($myRestaurant, OrderStatus::Preparing);

    $this->actingAs($rider)->get(route('rider.orders.index'))
        ->assertInertia(fn ($page) => $page
            ->component('rider/orders')
            ->has('orders', 0));

    expect($stillPreparing->fresh()->status)->toBe(OrderStatus::Preparing);
});

test('a rider can pick up an awaiting delivery order', function () {
    $restaurant = Restaurant::factory()->create();
    $rider = riderAt($restaurant);

    $this->travelTo(now()->startOfMinute());
    $order = deliveryOrderIn($restaurant, OrderStatus::AwaitingDelivery);
    $this->travelBack();

    $this->travelTo($order->updated_at->copy()->addSeconds(60));
    $this->actingAs($rider)
        ->post(route('rider.orders.pickup', ['order' => $order->id]))
        ->assertRedirect();
    $this->travelBack();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::OutForDelivery);

    $history = $order->statusHistory()->where('status', 'out_for_delivery')->first();
    expect($history)->not->toBeNull()
        ->and($history->previous_status)->toBe(OrderStatus::AwaitingDelivery)
        ->and($history->duration_in_previous_status)->toBe(60);
});

test('a rider cannot pick up an order that is not awaiting delivery', function () {
    $restaurant = Restaurant::factory()->create();
    $rider = riderAt($restaurant);
    $order = deliveryOrderIn($restaurant, OrderStatus::Preparing);

    $this->actingAs($rider)
        ->post(route('rider.orders.pickup', ['order' => $order->id]))
        ->assertStatus(422);

    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);
});

test('a rider from another restaurant cannot pick up the order', function () {
    $restaurant = Restaurant::factory()->create();
    $order = deliveryOrderIn($restaurant, OrderStatus::AwaitingDelivery);
    $otherRider = riderAt(Restaurant::factory()->create());

    $this->actingAs($otherRider)
        ->post(route('rider.orders.pickup', ['order' => $order->id]))
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingDelivery)
        ->and($order->statusHistory()->where('status', 'out_for_delivery')->count())->toBe(0);
});

test('pickup is first to act wins with no assignment record', function () {
    $restaurant = Restaurant::factory()->create();
    $riderA = riderAt($restaurant);
    $riderB = riderAt($restaurant);
    $order = deliveryOrderIn($restaurant, OrderStatus::AwaitingDelivery);

    $this->actingAs($riderA)
        ->post(route('rider.orders.pickup', ['order' => $order->id]))
        ->assertRedirect();

    $this->actingAs($riderB)
        ->post(route('rider.orders.pickup', ['order' => $order->id]))
        ->assertStatus(422)
        ->assertSessionHasNoErrors();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::OutForDelivery)
        ->and($order->statusHistory()->where('status', 'out_for_delivery')->count())->toBe(1);
});

test('a rider marks an out for delivery order delivered', function () {
    $restaurant = Restaurant::factory()->create();
    $rider = riderAt($restaurant);

    $this->travelTo(now()->startOfMinute());
    $order = deliveryOrderIn($restaurant, OrderStatus::OutForDelivery);
    $this->travelBack();

    $this->travelTo($order->updated_at->copy()->addSeconds(25));
    $this->actingAs($rider)
        ->post(route('rider.orders.deliver', ['order' => $order->id]))
        ->assertRedirect();
    $this->travelBack();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Closed);

    $history = $order->statusHistory()->where('status', 'closed')->first();
    expect($history)->not->toBeNull()
        ->and($history->previous_status)->toBe(OrderStatus::OutForDelivery)
        ->and($history->duration_in_previous_status)->toBe(25);
});

test('a rider cannot deliver an order that is not out for delivery', function () {
    $restaurant = Restaurant::factory()->create();
    $rider = riderAt($restaurant);
    $order = deliveryOrderIn($restaurant, OrderStatus::AwaitingDelivery);

    $this->actingAs($rider)
        ->post(route('rider.orders.deliver', ['order' => $order->id]))
        ->assertStatus(422);

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingDelivery)
        ->and($order->statusHistory()->where('status', 'closed')->count())->toBe(0);
});

test('users without the rider role are forbidden', function () {
    $restaurant = Restaurant::factory()->create();
    $order = deliveryOrderIn($restaurant, OrderStatus::AwaitingDelivery);

    $this->actingAs(User::factory()->create())
        ->post(route('rider.orders.pickup', ['order' => $order->id]))
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingDelivery);
});

test('a guest is redirected to login', function () {
    $restaurant = Restaurant::factory()->create();
    $order = deliveryOrderIn($restaurant, OrderStatus::AwaitingDelivery);

    $this->post(route('rider.orders.pickup', ['order' => $order->id]))
        ->assertRedirect(route('login'));

    $this->post(route('rider.orders.deliver', ['order' => $order->id]))
        ->assertRedirect(route('login'));
});

test('an unknown order is not found', function () {
    $restaurant = Restaurant::factory()->create();
    $rider = riderAt($restaurant);

    $this->actingAs($rider)
        ->post(route('rider.orders.pickup', ['order' => 999999]))
        ->assertNotFound();
});
