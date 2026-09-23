<?php

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Chef;
use App\Models\DeliveryAddress;
use App\Models\Dish;
use App\Models\DishOption;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\OrderKitchen;
use App\Models\Restaurant;
use App\Models\ServingSize;
use App\Models\User;

/**
 * Create a dish belonging to exactly one restaurant.
 */
function dishFor(Restaurant $restaurant, ?string $price = null): Dish
{
    return Dish::factory()
        ->for(Category::factory()->for($restaurant, 'restaurant'), 'category')
        ->for(Kitchen::factory()->for($restaurant, 'restaurant'), 'kitchen')
        ->create($price !== null ? ['price' => $price] : []);
}

test('a customer can place a delivery order and pricing is computed server-side', function () {
    config(['orders.vat_rate' => '0.15', 'orders.delivery_fee' => '10.00']);

    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create();
    $dish = dishFor($restaurant, price: '10.00');
    $servingSize = ServingSize::factory()->for($dish)->create(['price' => '5.00']);
    $optionA = DishOption::factory()->for($dish)->create(['price' => '2.00']);
    $optionB = DishOption::factory()->for($dish)->create(['price' => '3.00']);
    $address = DeliveryAddress::factory()->for($user, 'user')->create();

    $response = $this->actingAs($user)->post(route('orders.store'), [
        'delivery_mode' => 'delivery',
        'delivery_address_id' => $address->id,
        'lines' => [
            [
                'dish_id' => $dish->id,
                'serving_size_id' => $servingSize->id,
                'option_ids' => [$optionA->id, $optionB->id],
                'quantity' => 3,
            ],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'restaurant_id' => $restaurant->id,
        'delivery_mode' => 'delivery',
        'delivery_address_id' => $address->id,
        'status' => OrderStatus::Pending->value,
        'subtotal' => '60.00',
        'vat' => '9.00',
        'delivery_fee' => '10.00',
        'total' => '79.00',
    ]);
    $this->assertDatabaseHas('order_dishes', [
        'order_id' => $order = Order::query()->sole()->id,
        'dish_id' => $dish->id,
        'serving_size_id' => $servingSize->id,
        'quantity' => 3,
        'unit_price' => '20.00',
        'total_price' => '60.00',
    ]);
    $this->assertDatabaseHas('order_dish_option', ['dish_option_id' => $optionA->id, 'unit_price' => '2.00']);
    $this->assertDatabaseHas('order_dish_option', ['dish_option_id' => $optionB->id, 'unit_price' => '3.00']);
});

test('pickup orders carry no delivery fee', function () {
    config(['orders.vat_rate' => '0.15', 'orders.delivery_fee' => '10.00']);

    $user = User::factory()->create();
    $dish = dishFor(Restaurant::factory()->create(), price: '40.00');

    $this->actingAs($user)->post(route('orders.store'), [
        'delivery_mode' => 'pickup',
        'lines' => [
            ['dish_id' => $dish->id, 'option_ids' => [], 'quantity' => 2],
        ],
    ])->assertRedirect();

    $this->assertDatabaseHas('orders', [
        'delivery_mode' => 'pickup',
        'delivery_address_id' => null,
        'subtotal' => '80.00',
        'vat' => '12.00',
        'delivery_fee' => '0.00',
        'total' => '92.00',
    ]);
});

test('client-sent price fields are ignored on order creation', function () {
    config(['orders.vat_rate' => '0.15', 'orders.delivery_fee' => '10.00']);

    $user = User::factory()->create();
    $dish = dishFor(Restaurant::factory()->create(), price: '10.00');
    $option = DishOption::factory()->for($dish)->create(['price' => '5.00']);
    $address = DeliveryAddress::factory()->for($user, 'user')->create();

    $this->actingAs($user)->post(route('orders.store'), [
        'delivery_mode' => 'delivery',
        'delivery_address_id' => $address->id,
        'subtotal' => '0.01',
        'vat' => '0.01',
        'delivery_fee' => '0.01',
        'total' => '0.01',
        'lines' => [
            [
                'dish_id' => $dish->id,
                'option_ids' => [$option->id],
                'quantity' => 2,
                'unit_price' => '0.01',
                'total_price' => '0.01',
            ],
        ],
    ])->assertRedirect();

    $this->assertDatabaseHas('order_dishes', [
        'quantity' => 2,
        'unit_price' => '15.00',
        'total_price' => '30.00',
    ]);
    $this->assertDatabaseHas('orders', [
        'subtotal' => '30.00',
        'vat' => '4.50',
        'delivery_fee' => '10.00',
        'total' => '44.50',
    ]);
});

test('an order whose dishes span more than one restaurant is rejected', function () {
    $user = User::factory()->create();
    $dishA = dishFor(Restaurant::factory()->create(), price: '10.00');
    $dishB = dishFor(Restaurant::factory()->create(), price: '10.00');

    $this->actingAs($user)->postJson(route('orders.store'), [
        'delivery_mode' => 'pickup',
        'lines' => [
            ['dish_id' => $dishA->id, 'option_ids' => [], 'quantity' => 1],
            ['dish_id' => $dishB->id, 'option_ids' => [], 'quantity' => 1],
        ],
    ])->assertUnprocessable();

    $this->assertDatabaseCount('orders', 0);
});

test('delivery orders require one of the customer s own delivery addresses', function () {
    $user = User::factory()->create();
    $dish = dishFor(Restaurant::factory()->create(), price: '10.00');

    $this->actingAs($user)->post(route('orders.store'), [
        'delivery_mode' => 'delivery',
        'lines' => [
            ['dish_id' => $dish->id, 'option_ids' => [], 'quantity' => 1],
        ],
    ])->assertInvalid('delivery_address_id');
});

test('delivery orders may not use another customer s delivery address', function () {
    $user = User::factory()->create();
    $address = DeliveryAddress::factory()->create();
    $dish = dishFor(Restaurant::factory()->create(), price: '10.00');

    $this->actingAs($user)->post(route('orders.store'), [
        'delivery_mode' => 'delivery',
        'delivery_address_id' => $address->id,
        'lines' => [
            ['dish_id' => $dish->id, 'option_ids' => [], 'quantity' => 1],
        ],
    ])->assertInvalid('delivery_address_id');
});

test('pickup orders may not carry a delivery address', function () {
    $user = User::factory()->create();
    $address = DeliveryAddress::factory()->for($user, 'user')->create();
    $dish = dishFor(Restaurant::factory()->create(), price: '10.00');

    $this->actingAs($user)->post(route('orders.store'), [
        'delivery_mode' => 'pickup',
        'delivery_address_id' => $address->id,
        'lines' => [
            ['dish_id' => $dish->id, 'option_ids' => [], 'quantity' => 1],
        ],
    ])->assertInvalid('delivery_address_id');
});

test('an order line for a different kitchen of the same restaurant creates two order kitchen rows', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create();
    $dishA = dishFor($restaurant, price: '10.00');
    $dishB = dishFor($restaurant, price: '5.00');

    $this->actingAs($user)->post(route('orders.store'), [
        'delivery_mode' => 'pickup',
        'lines' => [
            ['dish_id' => $dishA->id, 'option_ids' => [], 'quantity' => 1],
            ['dish_id' => $dishB->id, 'option_ids' => [], 'quantity' => 1],
        ],
    ])->assertRedirect();

    $order = Order::query()->sole();
    expect(OrderKitchen::where('order_id', $order->id)->count())->toBe(2)
        ->and(OrderKitchen::query()->whereNotNull('accepted_at')->count())->toBe(0);
});

test('a serving size from another dish is rejected', function () {
    $user = User::factory()->create();
    $dish = dishFor(Restaurant::factory()->create(), price: '10.00');
    $servingSize = ServingSize::factory()->for(dishFor(Restaurant::factory()->create()))->create();

    $this->actingAs($user)->post(route('orders.store'), [
        'delivery_mode' => 'pickup',
        'lines' => [
            ['dish_id' => $dish->id, 'serving_size_id' => $servingSize->id, 'option_ids' => [], 'quantity' => 1],
        ],
    ])->assertInvalid('lines.0.serving_size_id');
});

test('an option from another dish is rejected', function () {
    $user = User::factory()->create();
    $dish = dishFor(Restaurant::factory()->create(), price: '10.00');
    $option = DishOption::factory()->for(dishFor(Restaurant::factory()->create()))->create();

    $this->actingAs($user)->post(route('orders.store'), [
        'delivery_mode' => 'pickup',
        'lines' => [
            ['dish_id' => $dish->id, 'option_ids' => [$option->id], 'quantity' => 1],
        ],
    ])->assertInvalid('lines.0.option_ids');
});

test('guests cannot place orders', function () {
    $this->post(route('orders.store'), [])->assertRedirect(route('login'));
});

test('a user holding another role can still place an order', function () {
    $chefUser = User::factory()->create();
    Chef::factory()->for($chefUser, 'user')->create();
    $dish = dishFor(Restaurant::factory()->create(), price: '10.00');

    $this->actingAs($chefUser)->post(route('orders.store'), [
        'delivery_mode' => 'pickup',
        'lines' => [
            ['dish_id' => $dish->id, 'option_ids' => [], 'quantity' => 1],
        ],
    ])->assertRedirect();

    $this->assertDatabaseHas('orders', ['user_id' => $chefUser->id, 'status' => OrderStatus::Pending->value]);
});

test('the order placement page renders with the restaurant menu', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create(['name_en' => 'Pizza Place']);
    $dish = dishFor($restaurant, price: '10.00');
    DishOption::factory()->for($dish)->create();
    ServingSize::factory()->for($dish)->create();

    $this->actingAs($user)->get(route('restaurants.order', $restaurant))
        ->assertInertia(fn ($page) => $page
            ->component('restaurants/order')
            ->where('restaurant.name_en', 'Pizza Place')
            ->has('categories', 1)
            ->has('categories.0.dishes', 1)
            ->has('addresses'));
});

test('an order may contain the same dish twice with different serving sizes as separate lines', function () {
    $user = User::factory()->create();
    $dish = dishFor(Restaurant::factory()->create(), price: '10.00');
    $small = ServingSize::factory()->for($dish)->create(['price' => '0.00']);
    $large = ServingSize::factory()->for($dish)->create(['price' => '5.00']);

    $this->actingAs($user)->post(route('orders.store'), [
        'delivery_mode' => 'pickup',
        'lines' => [
            ['dish_id' => $dish->id, 'serving_size_id' => $small->id, 'option_ids' => [], 'quantity' => 1],
            ['dish_id' => $dish->id, 'serving_size_id' => $large->id, 'option_ids' => [], 'quantity' => 2],
        ],
    ])->assertRedirect();

    $order = Order::query()->sole();
    $this->assertDatabaseCount('order_dishes', 2);
    $this->assertDatabaseHas('order_dishes', [
        'order_id' => $order->id,
        'dish_id' => $dish->id,
        'serving_size_id' => $small->id,
        'quantity' => 1,
        'unit_price' => '10.00',
    ]);
    $this->assertDatabaseHas('order_dishes', [
        'order_id' => $order->id,
        'dish_id' => $dish->id,
        'serving_size_id' => $large->id,
        'quantity' => 2,
        'unit_price' => '15.00',
    ]);
});
