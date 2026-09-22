<?php

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Company;
use App\Models\Manager;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;

/**
 * Create an order for the restaurant, advanced (via direct transitions,
 * bypassing dish-level machinery) to the given status.
 */
function orderInStatus(Restaurant $restaurant, OrderStatus $status, ?User $customer = null): Order
{
    $order = Order::factory()
        ->for($customer ?? User::factory(), 'user')
        ->for($restaurant)
        ->create();

    $chain = match ($status) {
        OrderStatus::Pending => [],
        OrderStatus::Confirmed => [OrderStatus::Confirmed],
        OrderStatus::Preparing => [OrderStatus::Confirmed, OrderStatus::Preparing],
        OrderStatus::AwaitingPickup => [OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::AwaitingPickup],
        default => throw new InvalidArgumentException("Unsupported status for this test helper: {$status->value}"),
    };

    foreach ($chain as $next) {
        $order->transitionTo($next);
    }

    return $order->fresh();
}

test('a customer can cancel their own pending order', function () {
    $user = User::factory()->create();

    $this->travelTo(now()->startOfMinute());
    $order = orderInStatus(Restaurant::factory()->create(), OrderStatus::Pending, $user);
    $this->travelBack();

    $this->travelTo($order->created_at->copy()->addSeconds(20));
    $this->actingAs($user)
        ->post(route('orders.cancel', ['order' => $order->id]))
        ->assertRedirect();
    $this->travelBack();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Cancelled);

    $history = $order->statusHistory()->where('status', 'cancelled')->first();
    expect($history)->not->toBeNull()
        ->and($history->previous_status)->toBe(OrderStatus::Pending)
        ->and($history->duration_in_previous_status)->toBe(20);
});

test('a customer cannot cancel another customers order', function () {
    $user = User::factory()->create();
    $order = orderInStatus(Restaurant::factory()->create(), OrderStatus::Pending);

    $this->actingAs($user)
        ->post(route('orders.cancel', ['order' => $order->id]))
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('a customer cannot cancel their own confirmed order', function () {
    $user = User::factory()->create();
    $order = orderInStatus(Restaurant::factory()->create(), OrderStatus::Confirmed, $user);

    $this->actingAs($user)
        ->post(route('orders.cancel', ['order' => $order->id]))
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);
});

test('a manager can cancel their companys preparing order', function () {
    $company = Company::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $company->id]);
    $restaurant = Restaurant::factory()->create(['company_id' => $company->id]);
    $order = orderInStatus($restaurant, OrderStatus::Preparing);

    $this->actingAs($manager)
        ->post(route('orders.cancel', ['order' => $order->id]))
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

test('a manager cannot cancel their companys order once awaiting pickup', function () {
    $company = Company::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $company->id]);
    $restaurant = Restaurant::factory()->create(['company_id' => $company->id]);
    $order = orderInStatus($restaurant, OrderStatus::AwaitingPickup);

    $this->actingAs($manager)
        ->post(route('orders.cancel', ['order' => $order->id]))
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPickup);
});

test('an admin can cancel any restaurants confirmed order', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    $order = orderInStatus(Restaurant::factory()->create(), OrderStatus::Confirmed);

    $this->actingAs($admin)
        ->post(route('orders.cancel', ['order' => $order->id]))
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

test('a guest is redirected to login', function () {
    $order = orderInStatus(Restaurant::factory()->create(), OrderStatus::Pending);

    $this->post(route('orders.cancel', ['order' => $order->id]))
        ->assertRedirect(route('login'));
});

test('the orders index shows only the customers own orders', function () {
    $user = User::factory()->create();
    $mine = orderInStatus(Restaurant::factory()->create(), OrderStatus::Pending, $user);
    orderInStatus(Restaurant::factory()->create(), OrderStatus::Pending);

    $this->actingAs($user)->get(route('orders.index'))
        ->assertInertia(fn ($page) => $page
            ->component('orders/index')
            ->has('orders', 1)
            ->where('orders.0.id', $mine->id)
            ->where('orders.0.can_cancel', true));
});

test('the orders index shows a manager their companys orders', function () {
    $company = Company::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $company->id]);
    $restaurant = Restaurant::factory()->create(['company_id' => $company->id]);

    $ours = orderInStatus($restaurant, OrderStatus::Preparing);
    orderInStatus(Restaurant::factory()->create(), OrderStatus::Preparing);

    $this->actingAs($manager)->get(route('orders.index'))
        ->assertInertia(fn ($page) => $page
            ->component('orders/index')
            ->has('orders', 1)
            ->where('orders.0.id', $ours->id)
            ->where('orders.0.can_cancel', true));
});

test('the orders index shows an admin every order', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    orderInStatus(Restaurant::factory()->create(), OrderStatus::Pending);
    orderInStatus(Restaurant::factory()->create(), OrderStatus::Preparing);

    $this->actingAs($admin)->get(route('orders.index'))
        ->assertInertia(fn ($page) => $page
            ->component('orders/index')
            ->has('orders', 2));
});

test('the orders index marks a non cancellable order accordingly', function () {
    $user = User::factory()->create();
    orderInStatus(Restaurant::factory()->create(), OrderStatus::Confirmed, $user);

    $this->actingAs($user)->get(route('orders.index'))
        ->assertInertia(fn ($page) => $page
            ->component('orders/index')
            ->where('orders.0.can_cancel', false));
});
