<?php

use App\Enums\OrderDishStatus;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Chef;
use App\Models\Company;
use App\Models\Dish;
use App\Models\Kitchen;
use App\Models\Manager;
use App\Models\Order;
use App\Models\OrderDish;
use App\Models\OrderKitchen;
use App\Models\Restaurant;
use App\Models\Rider;
use App\Models\User;
use App\Models\Waiter;

/**
 * Create a restaurant belonging to the given company.
 */
function visRestaurantIn(Company $company): Restaurant
{
    return Restaurant::factory()->for($company, 'company')->create();
}

/**
 * Create a manager of the given company.
 */
function visManagerIn(Company $company): User
{
    $user = User::factory()->create();
    Manager::factory()->for($user, 'user')->for($company, 'company')->create();

    return $user;
}

/**
 * Create an order placed by the given customer for the restaurant, with a
 * dish line and a transition to CONFIRMED so the history timeline has depth.
 */
function visOrderAt(Restaurant $restaurant, string $deliveryMode, ?User $customer = null): Order
{
    $customer ??= User::factory()->create();

    $order = Order::factory()->for($customer, 'user')->for($restaurant)->create([
        'delivery_mode' => $deliveryMode,
    ]);

    OrderDish::factory()->for($order, 'order')->create([
        'status' => OrderDishStatus::Ready->value,
    ]);

    $order->transitionTo(OrderStatus::Confirmed);

    return $order;
}

test('a customer sees only their own orders in the list', function () {
    $mine = visOrderAt(visRestaurantIn(Company::factory()->create()), 'pickup');
    visOrderAt(visRestaurantIn(Company::factory()->create()), 'pickup');

    $this->actingAs($mine->user)
        ->get(route('orders.index'))
        ->assertInertia(fn ($page) => $page
            ->component('orders/index')
            ->has('orders', 1)
            ->where('orders.0.id', $mine->id));
});

test('a customer can view their own order detail with the status timeline', function () {
    $order = visOrderAt(visRestaurantIn(Company::factory()->create()), 'pickup', User::factory()->create());

    $this->actingAs($order->user)
        ->get(route('orders.show', ['order' => $order->id]))
        ->assertInertia(fn ($page) => $page
            ->component('orders/show')
            ->where('order.id', $order->id)
            ->has('order.status_history', 2)
            ->has('order.status_history.0', fn ($row) => $row
                ->where('status', 'pending')
                ->etc())
            ->has('order.status_history.1', fn ($row) => $row
                ->where('status', 'confirmed')
                ->where('previous_status', 'pending')
                ->etc()));
});

test('a customer cannot view an order placed by a different customer', function () {
    $order = visOrderAt(visRestaurantIn(Company::factory()->create()), 'pickup');

    $this->actingAs(User::factory()->create())
        ->get(route('orders.show', ['order' => $order->id]))
        ->assertForbidden();
});

test('an admin sees every order and can view any order', function () {
    $orderA = visOrderAt(visRestaurantIn(Company::factory()->create()), 'pickup');
    $orderB = visOrderAt(visRestaurantIn(Company::factory()->create()), 'delivery');
    $user = User::factory()->create();
    $user->admin()->create();

    $this->actingAs($user)
        ->get(route('orders.index'))
        ->assertInertia(fn ($page) => $page
            ->component('orders/index')
            ->has('orders', 2));

    $this->actingAs($user)
        ->get(route('orders.show', ['order' => $orderA->id]))
        ->assertOk();
});

test('a manager sees all orders across their company s restaurants only', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $manager = visManagerIn($companyA);

    $oursA = visOrderAt(visRestaurantIn($companyA), 'delivery');
    $oursB = visOrderAt(visRestaurantIn($companyA), 'delivery');
    visOrderAt(visRestaurantIn($companyB), 'delivery');

    $this->actingAs($manager)
        ->get(route('orders.index'))
        ->assertInertia(fn ($page) => $page
            ->component('orders/index')
            ->has('orders', 2)
            ->has('orders', fn ($orders) => $orders
                ->where('0.id', $oursA->id)
                ->where('1.id', $oursB->id)));
});

test('a manager cannot view an order from another company', function () {
    $order = visOrderAt(visRestaurantIn(Company::factory()->create()), 'pickup');

    $this->actingAs(visManagerIn(Company::factory()->create()))
        ->get(route('orders.show', ['order' => $order->id]))
        ->assertForbidden();
});

test('a chef can view orders containing dishes from their kitchens', function () {
    $restaurant = visRestaurantIn(Company::factory()->create());
    $kitchen = Kitchen::factory()->for($restaurant, 'restaurant')->create();
    $order = visOrderAt($restaurant, 'delivery', User::factory()->create());

    $dish = Dish::factory()->for($kitchen, 'kitchen')
        ->for(Category::factory()->for($restaurant, 'restaurant'), 'category')->create();
    OrderDish::factory()->for($order, 'order')->for($dish, 'dish')->create();
    OrderKitchen::factory()->for($order, 'order')->for($kitchen, 'kitchen')->create();

    $user = User::factory()->create();
    $chef = Chef::factory()->for($user, 'user')->create();
    $chef->kitchens()->attach($kitchen->id, ['assigned_at' => now()]);

    $this->actingAs($user)
        ->get(route('orders.show', ['order' => $order->id]))
        ->assertOk();

    $otherKitchen = Kitchen::factory()->for($restaurant, 'restaurant')->create();
    $otherOrder = visOrderAt($restaurant, 'delivery');
    $otherDish = Dish::factory()->for($otherKitchen, 'kitchen')
        ->for(Category::factory()->for($restaurant, 'restaurant'), 'category')->create();
    OrderDish::factory()->for($otherOrder, 'order')->for($otherDish, 'dish')->create();

    $this->actingAs($user)
        ->get(route('orders.show', ['order' => $otherOrder->id]))
        ->assertForbidden();
});

test('a chef can view an order through its dish lines even without an order kitchen row', function () {
    $restaurant = visRestaurantIn(Company::factory()->create());
    $kitchen = Kitchen::factory()->for($restaurant, 'restaurant')->create();
    $order = visOrderAt($restaurant, 'delivery', User::factory()->create());

    $dish = Dish::factory()->for($kitchen, 'kitchen')
        ->for(Category::factory()->for($restaurant, 'restaurant'), 'category')->create();
    OrderDish::factory()->for($order, 'order')->for($dish, 'dish')->create();

    $user = User::factory()->create();
    $chef = Chef::factory()->for($user, 'user')->create();
    $chef->kitchens()->attach($kitchen->id, ['assigned_at' => now()]);

    $this->actingAs($user)
        ->get(route('orders.show', ['order' => $order->id]))
        ->assertOk();
});

test('a rider can view delivery orders of their restaurant only', function () {
    $restaurantA = visRestaurantIn(Company::factory()->create());
    $restaurantB = visRestaurantIn(Company::factory()->create());
    $riderOrder = visOrderAt($restaurantA, 'delivery');
    $pickupOrder = visOrderAt($restaurantA, 'pickup');
    $rider = User::factory()->create();
    Rider::factory()->for($rider, 'user')->for($restaurantA, 'restaurant')->create();

    $this->actingAs($rider)
        ->get(route('orders.show', ['order' => $riderOrder->id]))
        ->assertOk();

    $this->actingAs($rider)
        ->get(route('orders.show', ['order' => $pickupOrder->id]))
        ->assertForbidden();

    $this->actingAs($rider)
        ->get(route('orders.show', ['order' => visOrderAt($restaurantB, 'delivery')->id]))
        ->assertForbidden();
});

test('a waiter can view orders of their restaurant only', function () {
    $restaurantA = visRestaurantIn(Company::factory()->create());
    $order = visOrderAt($restaurantA, 'pickup');
    $user = User::factory()->create();
    Waiter::factory()->for($user, 'user')->for($restaurantA, 'restaurant')->create();

    $this->actingAs($user)
        ->get(route('orders.show', ['order' => $order->id]))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('orders.show', ['order' => visOrderAt(visRestaurantIn(Company::factory()->create()), 'pickup')->id]))
        ->assertForbidden();
});

test('guests are redirected to login', function () {
    $order = visOrderAt(visRestaurantIn(Company::factory()->create()), 'pickup');

    $this->get(route('orders.show', ['order' => $order->id]))
        ->assertRedirect(route('login'));
});
