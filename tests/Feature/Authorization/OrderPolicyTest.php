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
function orderAt(Restaurant $restaurant, OrderStatus $status, string $deliveryMode = 'pickup'): Order
{
    $order = Order::factory()
        ->for(User::factory(), 'user')
        ->for($restaurant)
        ->create(['delivery_mode' => $deliveryMode]);

    $chain = match ($status) {
        OrderStatus::Pending => [],
        OrderStatus::Confirmed => [OrderStatus::Confirmed],
        OrderStatus::Preparing => [OrderStatus::Confirmed, OrderStatus::Preparing],
        OrderStatus::AwaitingPickup => [OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::AwaitingPickup],
        OrderStatus::AwaitingDelivery => [OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::AwaitingDelivery],
        OrderStatus::OutForDelivery => [OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::AwaitingDelivery, OrderStatus::OutForDelivery],
        OrderStatus::Closed => [OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::AwaitingPickup, OrderStatus::Closed],
        OrderStatus::Cancelled => [OrderStatus::Cancelled],
    };

    foreach ($chain as $next) {
        $order->transitionTo($next);
    }

    return $order->fresh();
}

test('a customer can cancel their own pending order', function () {
    $user = User::factory()->create();
    $order = orderAt(Restaurant::factory()->create(), OrderStatus::Pending);
    $order->update(['user_id' => $user->id]);

    expect($user->can('cancel', $order))->toBeTrue();
});

test('a customer cannot cancel their own order once past pending', function (OrderStatus $status) {
    $user = User::factory()->create();
    $order = orderAt(Restaurant::factory()->create(), $status);
    $order->update(['user_id' => $user->id]);

    expect($user->can('cancel', $order))->toBeFalse();
})->with([
    OrderStatus::Confirmed,
    OrderStatus::Preparing,
    OrderStatus::AwaitingPickup,
    OrderStatus::AwaitingDelivery,
    OrderStatus::OutForDelivery,
    OrderStatus::Closed,
]);

test('a customer cannot cancel another customers order', function () {
    $user = User::factory()->create();
    $order = orderAt(Restaurant::factory()->create(), OrderStatus::Pending);

    expect($user->can('cancel', $order))->toBeFalse();
});

test('a manager can cancel their companys orders while pending, confirmed, or preparing', function (OrderStatus $status) {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    Manager::factory()->create(['user_id' => $user->id, 'company_id' => $company->id]);
    $restaurant = Restaurant::factory()->create(['company_id' => $company->id]);
    $order = orderAt($restaurant, $status);

    expect($user->can('cancel', $order))->toBeTrue();
})->with([
    OrderStatus::Pending,
    OrderStatus::Confirmed,
    OrderStatus::Preparing,
]);

test('a manager cannot cancel their companys orders once awaiting delivery, awaiting pickup, or later', function (OrderStatus $status) {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    Manager::factory()->create(['user_id' => $user->id, 'company_id' => $company->id]);
    $restaurant = Restaurant::factory()->create(['company_id' => $company->id]);
    $order = orderAt($restaurant, $status);

    expect($user->can('cancel', $order))->toBeFalse();
})->with([
    OrderStatus::AwaitingPickup,
    OrderStatus::AwaitingDelivery,
    OrderStatus::OutForDelivery,
    OrderStatus::Closed,
]);

test('a manager cannot cancel another companys order', function () {
    $user = User::factory()->create();
    Manager::factory()->create(['user_id' => $user->id, 'company_id' => Company::factory()->create()->id]);
    $order = orderAt(Restaurant::factory()->create(), OrderStatus::Pending);

    expect($user->can('cancel', $order))->toBeFalse();
});

test('an admin can cancel any order while pending, confirmed, or preparing', function (OrderStatus $status) {
    $user = User::factory()->create();
    Admin::factory()->create(['user_id' => $user->id]);
    $order = orderAt(Restaurant::factory()->create(), $status);

    expect($user->can('cancel', $order))->toBeTrue();
})->with([
    OrderStatus::Pending,
    OrderStatus::Confirmed,
    OrderStatus::Preparing,
]);

test('an admin cannot cancel an order once awaiting delivery, awaiting pickup, or later', function (OrderStatus $status) {
    $user = User::factory()->create();
    Admin::factory()->create(['user_id' => $user->id]);
    $order = orderAt(Restaurant::factory()->create(), $status);

    expect($user->can('cancel', $order))->toBeFalse();
})->with([
    OrderStatus::AwaitingPickup,
    OrderStatus::AwaitingDelivery,
    OrderStatus::OutForDelivery,
    OrderStatus::Closed,
]);

test('nobody can cancel an already cancelled order', function () {
    $user = User::factory()->create();
    Admin::factory()->create(['user_id' => $user->id]);
    $order = orderAt(Restaurant::factory()->create(), OrderStatus::Cancelled);

    expect($user->can('cancel', $order))->toBeFalse();
});
