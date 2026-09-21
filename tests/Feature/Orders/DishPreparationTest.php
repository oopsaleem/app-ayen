<?php

use App\Enums\OrderDishStatus;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Chef;
use App\Models\Dish;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\OrderDish;
use App\Models\OrderKitchen;
use App\Models\Restaurant;
use App\Models\User;

/**
 * Create a chef user assigned to the given kitchen.
 */
function prepChefIn(Kitchen $kitchen): User
{
    $user = User::factory()->create();
    $chef = Chef::factory()->for($user, 'user')->create();
    $chef->kitchens()->attach($kitchen->id, ['assigned_at' => now()]);

    return $user;
}

/**
 * Create a dish belonging to the given kitchen.
 */
function prepDishIn(Kitchen $kitchen): Dish
{
    return Dish::factory()
        ->for($kitchen, 'kitchen')
        ->for(Category::factory()->for($kitchen->restaurant, 'restaurant'), 'category')
        ->create();
}

/**
 * Create a confirmed order with every kitchen already accepted, so that
 * dish preparation may begin.
 */
function prepConfirmedOrder(Restaurant $restaurant, string $deliveryMode, Kitchen ...$kitchens): Order
{
    $order = Order::factory()
        ->for(User::factory(), 'user')
        ->for($restaurant)
        ->create([
            'delivery_mode' => $deliveryMode,
            'subtotal' => '10.00',
            'vat' => '1.00',
            'delivery_fee' => '0.00',
            'total' => '11.00',
        ]);

    foreach ($kitchens as $kitchen) {
        OrderKitchen::factory()->for($order, 'order')->for($kitchen, 'kitchen')->create([
            'accepted_at' => now(),
            'accepted_by' => $order->user_id,
        ]);
    }

    $order->transitionTo(OrderStatus::Confirmed);

    return $order;
}

/**
 * Create one order dish line for the order with its dish in the given kitchen.
 */
function prepOrderDishLine(Order $order, Kitchen $kitchen): OrderDish
{
    return OrderDish::factory()
        ->for($order, 'order')
        ->for(prepDishIn($kitchen), 'dish')
        ->create();
}

test('marking a dish preparing while the order is still pending is rejected', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->for($restaurant, 'restaurant')->create();
    $chef = prepChefIn($kitchen);
    $order = Order::factory()->for(User::factory(), 'user')->for($restaurant)->create([
        'delivery_mode' => 'pickup',
        'status' => OrderStatus::Pending,
    ]);
    OrderKitchen::factory()->for($order, 'order')->for($kitchen, 'kitchen')->create();
    $line = prepOrderDishLine($order, $kitchen);

    $this->actingAs($chef)
        ->patchJson(route('chef.orders.dishes.update', ['order' => $order->id, 'orderDish' => $line->id]), [
            'status' => 'preparing',
        ])
        ->assertStatus(422);

    expect($line->fresh()->status)->toBe(OrderDishStatus::Pending)
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->statusHistory()->count())->toBe(1);
});

test('marking the first dish preparing auto-advances the confirmed order to preparing', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->for($restaurant, 'restaurant')->create();
    $chef = prepChefIn($kitchen);

    $this->travelTo(now()->startOfMinute());
    $order = prepConfirmedOrder($restaurant, 'pickup', $kitchen);
    $line = prepOrderDishLine($order, $kitchen);
    $this->travelBack();

    $this->travelTo($order->created_at->copy()->addSeconds(30));
    $this->actingAs($chef)
        ->patchJson(route('chef.orders.dishes.update', ['order' => $order->id, 'orderDish' => $line->id]), [
            'status' => 'preparing',
        ])
        ->assertOk();
    $this->travelBack();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Preparing)
        ->and($line->fresh()->status)->toBe(OrderDishStatus::Preparing);

    $history = $order->statusHistory()->where('status', 'preparing')->first();
    expect($history)->not->toBeNull()
        ->and($history->previous_status)->toBe(OrderStatus::Confirmed)
        ->and($history->duration_in_previous_status)->toBe(30);
});

test('the order reaches awaiting delivery only once all dishes are ready', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->for($restaurant, 'restaurant')->create();
    $chef = prepChefIn($kitchen);
    $order = prepConfirmedOrder($restaurant, 'delivery', $kitchen);
    $lineA = prepOrderDishLine($order, $kitchen);
    $lineB = prepOrderDishLine($order, $kitchen);

    $mark = fn (OrderDish $line, string $status) => $this->actingAs($chef)
        ->patchJson(route('chef.orders.dishes.update', ['order' => $order->id, 'orderDish' => $line->id]), [
            'status' => $status,
        ])
        ->assertOk();

    $mark($lineA, 'preparing');
    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);

    $mark($lineA, 'ready');
    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);

    $mark($lineB, 'preparing');
    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);

    $mark($lineB, 'ready');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::AwaitingDelivery)
        ->and($order->dishes()->where('status', 'ready')->count())->toBe(2);

    $history = $order->statusHistory()->where('status', 'awaiting_delivery')->first();
    expect($history)->not->toBeNull()
        ->and($history->previous_status)->toBe(OrderStatus::Preparing);
});

test('awaiting pickup is chosen for pickup orders when all dishes are ready', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->for($restaurant, 'restaurant')->create();
    $chef = prepChefIn($kitchen);
    $order = prepConfirmedOrder($restaurant, 'pickup', $kitchen);
    $line = prepOrderDishLine($order, $kitchen);

    foreach (['preparing', 'ready'] as $status) {
        $this->actingAs($chef)
            ->patchJson(route('chef.orders.dishes.update', ['order' => $order->id, 'orderDish' => $line->id]), [
                'status' => $status,
            ])
            ->assertOk();
    }

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::AwaitingPickup);
});

test('a chef of a kitchen that does not own the dish is forbidden', function () {
    $restaurant = Restaurant::factory()->create();
    $myKitchen = Kitchen::factory()->for($restaurant, 'restaurant')->create();
    $otherKitchen = Kitchen::factory()->for($restaurant, 'restaurant')->create();
    $chef = prepChefIn($myKitchen);
    $order = prepConfirmedOrder($restaurant, 'pickup', $myKitchen, $otherKitchen);
    $line = prepOrderDishLine($order, $otherKitchen);

    $this->actingAs($chef)
        ->patchJson(route('chef.orders.dishes.update', ['order' => $order->id, 'orderDish' => $line->id]), [
            'status' => 'preparing',
        ])
        ->assertForbidden();

    expect($line->fresh()->status)->toBe(OrderDishStatus::Pending)
        ->and($order->fresh()->status)->toBe(OrderStatus::Confirmed);
});

test('a dish cannot skip preparing and jump straight to ready', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->for($restaurant, 'restaurant')->create();
    $chef = prepChefIn($kitchen);
    $order = prepConfirmedOrder($restaurant, 'pickup', $kitchen);
    $line = prepOrderDishLine($order, $kitchen);

    $this->actingAs($chef)
        ->patchJson(route('chef.orders.dishes.update', ['order' => $order->id, 'orderDish' => $line->id]), [
            'status' => 'ready',
        ])
        ->assertStatus(422);

    expect($line->fresh()->status)->toBe(OrderDishStatus::Pending)
        ->and($order->fresh()->status)->toBe(OrderStatus::Confirmed);
});

test('an already ready dish cannot be marked again', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->for($restaurant, 'restaurant')->create();
    $chef = prepChefIn($kitchen);
    $order = prepConfirmedOrder($restaurant, 'pickup', $kitchen);
    $line = prepOrderDishLine($order, $kitchen);

    foreach (['preparing', 'ready'] as $status) {
        $this->actingAs($chef)
            ->patchJson(route('chef.orders.dishes.update', ['order' => $order->id, 'orderDish' => $line->id]), [
                'status' => $status,
            ])
            ->assertOk();
    }

    $this->actingAs($chef)
        ->patchJson(route('chef.orders.dishes.update', ['order' => $order->id, 'orderDish' => $line->id]), [
            'status' => 'ready',
        ])
        ->assertStatus(422);

    expect($order->fresh()->status->value)->toBe(OrderStatus::AwaitingPickup->value)
        ->and($order->statusHistory()->count())->toBe(4);
});

test('kitchens may still mark their dishes preparing once the order is preparing', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->for($restaurant, 'restaurant')->create();
    $chef = prepChefIn($kitchen);
    $order = prepConfirmedOrder($restaurant, 'pickup', $kitchen);
    $lineA = prepOrderDishLine($order, $kitchen);
    $lineB = prepOrderDishLine($order, $kitchen);

    $this->actingAs($chef)
        ->patchJson(route('chef.orders.dishes.update', ['order' => $order->id, 'orderDish' => $lineA->id]), [
            'status' => 'preparing',
        ])
        ->assertOk();

    $this->actingAs($chef)
        ->patchJson(route('chef.orders.dishes.update', ['order' => $order->id, 'orderDish' => $lineB->id]), [
            'status' => 'preparing',
        ])
        ->assertOk();

    expect($lineB->fresh()->status)->toBe(OrderDishStatus::Preparing)
        ->and($order->statusHistory()->where('status', 'preparing')->count())->toBe(1);
});

test('the chef orders page lists confirmed orders with per-dish status', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->for($restaurant, 'restaurant')->create();
    $chef = prepChefIn($kitchen);
    $order = prepConfirmedOrder($restaurant, 'pickup', $kitchen);
    prepOrderDishLine($order, $kitchen);
    prepOrderDishLine($order, $kitchen);

    $response = $this->actingAs($chef)->get(route('chef.orders.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('chef/orders')
        ->has('orders', 1)
        ->where('orders.0.status', 'confirmed')
        ->where('orders.0.kitchens.0.dishes.0.status', OrderDishStatus::Pending->value)
        ->where('orders.0.kitchens.0.dishes.1.status', OrderDishStatus::Pending->value));
});
