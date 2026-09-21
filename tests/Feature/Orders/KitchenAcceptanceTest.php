<?php

use App\Enums\OrderStatus;
use App\Models\Chef;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\OrderKitchen;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Create a kitchen belonging to the given restaurant.
 */
function kitchenIn(Restaurant $restaurant): Kitchen
{
    return Kitchen::factory()->for($restaurant, 'restaurant')->create();
}

/**
 * Create a chef and assign them to the given kitchen.
 */
function chefIn(Kitchen $kitchen): User
{
    $user = User::factory()->create();
    $chef = Chef::factory()->for($user, 'user')->create();
    $chef->kitchens()->attach($kitchen->id, ['assigned_at' => now()]);

    return $user;
}

/**
 * Create a pending order touching the given kitchens.
 */
function pendingOrder(Restaurant $restaurant, Kitchen ...$kitchens): Order
{
    $order = Order::factory()
        ->for(User::factory(), 'user')
        ->for($restaurant)
        ->create(['delivery_mode' => 'pickup']);

    foreach ($kitchens as $kitchen) {
        OrderKitchen::factory()->for($order, 'order')->for($kitchen, 'kitchen')->create();
    }

    return $order;
}

test('a chef of a kitchen in the order can accept it for their kitchen', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = kitchenIn($restaurant);
    $chef = chefIn($kitchen);
    $order = pendingOrder($restaurant, $kitchen);

    $response = $this->actingAs($chef)
        ->post(route('chef.orders.accept', ['order' => $order->id, 'kitchen' => $kitchen->id]));

    $response->assertRedirect();
    $this->assertDatabaseHas('order_kitchens', [
        'order_id' => $order->id,
        'kitchen_id' => $kitchen->id,
        'accepted_by' => $chef->id,
    ]);
    expect(OrderKitchen::query()->whereNotNull('accepted_at')->count())->toBe(1);
});

test('an unaccepted order stays pending while some kitchens have not accepted', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchenA = kitchenIn($restaurant);
    $kitchenB = kitchenIn($restaurant);
    $order = pendingOrder($restaurant, $kitchenA, $kitchenB);

    $this->actingAs(chefIn($kitchenA))
        ->post(route('chef.orders.accept', ['order' => $order->id, 'kitchen' => $kitchenA->id]))
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->statusHistory()->count())->toBe(1);
});

test('the order auto-confirms when the last kitchen accepts', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchenA = kitchenIn($restaurant);
    $kitchenB = kitchenIn($restaurant);

    $this->travelTo(now()->startOfMinute());
    $order = pendingOrder($restaurant, $kitchenA, $kitchenB);

    $this->actingAs(chefIn($kitchenA))
        ->post(route('chef.orders.accept', ['order' => $order->id, 'kitchen' => $kitchenA->id]))
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);

    $this->travel(90)->seconds();
    $this->actingAs(chefIn($kitchenB))
        ->post(route('chef.orders.accept', ['order' => $order->id, 'kitchen' => $kitchenB->id]))
        ->assertRedirect();
    $this->travelBack();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Confirmed);

    $history = $order->statusHistory()->where('status', 'confirmed')->first();
    expect($history)->not->toBeNull()
        ->and($history->previous_status)->toBe(OrderStatus::Pending)
        ->and($history->duration_in_previous_status)->toBe(90);
});

test('a second chef of the same kitchen is a no-op and the first chef wins', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = kitchenIn($restaurant);
    $order = pendingOrder($restaurant, $kitchen);
    $firstChef = chefIn($kitchen);
    $secondChef = chefIn($kitchen);

    $this->actingAs($firstChef)
        ->post(route('chef.orders.accept', ['order' => $order->id, 'kitchen' => $kitchen->id]))
        ->assertRedirect();

    $acceptedAt = DB::table('order_kitchens')->where('order_id', $order->id)->value('accepted_at');

    // The second chef does not get a destructive error.
    $this->actingAs($secondChef)
        ->post(route('chef.orders.accept', ['order' => $order->id, 'kitchen' => $kitchen->id]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $row = DB::table('order_kitchens')->where('order_id', $order->id)->first();
    expect($row->accepted_at)->toBe($acceptedAt)
        ->and($row->accepted_by)->toBe($firstChef->id);

    // No extra history row, no extra transition.
    expect($order->statusHistory()->count())->toBe(2)
        ->and($order->fresh()->status)->toBe(OrderStatus::Confirmed);
});

test('a chef of a kitchen belonging to another restaurant is forbidden', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = kitchenIn($restaurant);
    $otherKitchen = kitchenIn(Restaurant::factory()->create());
    $order = pendingOrder($restaurant, $kitchen);

    $response = $this->actingAs(chefIn($otherKitchen))
        ->post(route('chef.orders.accept', ['order' => $order->id, 'kitchen' => $kitchen->id]));

    $response->assertForbidden();
    expect(OrderKitchen::query()->whereNotNull('accepted_at')->count())->toBe(0);
});

test('a user without the chef role is forbidden', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = kitchenIn($restaurant);
    $order = pendingOrder($restaurant, $kitchen);

    $this->actingAs(User::factory()->create())
        ->post(route('chef.orders.accept', ['order' => $order->id, 'kitchen' => $kitchen->id]))
        ->assertForbidden();

    expect(OrderKitchen::query()->whereNotNull('accepted_at')->count())->toBe(0);
});

test('guests are redirected to login', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = kitchenIn($restaurant);
    $order = pendingOrder($restaurant, $kitchen);

    $this->post(route('chef.orders.accept', ['order' => $order->id, 'kitchen' => $kitchen->id]))
        ->assertRedirect(route('login'));
});

test('accepting for a kitchen that is not part of the order is not found', function () {
    $restaurant = Restaurant::factory()->create();
    $order = pendingOrder($restaurant);
    $outsideKitchen = kitchenIn($restaurant);

    $this->actingAs(chefIn($outsideKitchen))
        ->post(route('chef.orders.accept', ['order' => $order->id, 'kitchen' => $outsideKitchen->id]))
        ->assertNotFound();
});

test('accepting a cancelled order is a clear rejection that changes nothing', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = kitchenIn($restaurant);
    $order = pendingOrder($restaurant, $kitchen);
    $order->forceFill(['status' => OrderStatus::Cancelled])->save();

    $this->actingAs(chefIn($kitchen))
        ->post(route('chef.orders.accept', ['order' => $order->id, 'kitchen' => $kitchen->id]))
        ->assertRedirect();

    $row = DB::table('order_kitchens')->where('order_id', $order->id)->first();
    expect($row->accepted_at)->toBeNull()
        ->and($order->statusHistory()->count())->toBe(1)
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

test('the chef orders page lists pending orders for the chef s kitchens', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = kitchenIn($restaurant);
    $order = pendingOrder($restaurant, $kitchen);

    $response = $this->actingAs(chefIn($kitchen))
        ->get(route('chef.orders.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('chef/orders')
        ->has('orders', 1)
        ->where('orders.0.id', $order->id)
        ->where('orders.0.restaurant.name_en', $restaurant->name_en)
        ->where('orders.0.kitchens.0.kitchen_id', $kitchen->id)
        ->where('orders.0.kitchens.0.accepted', false));
});

test('the chef orders page does not show orders for other kitchens', function () {
    $restaurant = Restaurant::factory()->create();
    $myKitchen = kitchenIn($restaurant);
    $otherKitchen = kitchenIn($restaurant);
    pendingOrder($restaurant, $otherKitchen);

    $response = $this->actingAs(chefIn($myKitchen))
        ->get(route('chef.orders.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('chef/orders')
        ->has('orders', 0));
});

test('non chefs cannot view the chef orders page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('chef.orders.index'))
        ->assertForbidden();
});
