<?php

use App\Models\Category;
use App\Models\Dish;
use App\Models\Kitchen;
use App\Models\Restaurant;
use App\Models\RestaurantVerification;

/**
 * Create a dish belonging to exactly one restaurant.
 */
function storefrontDishFor(Restaurant $restaurant, ?string $price = null): Dish
{
    return Dish::factory()
        ->for(Category::factory()->for($restaurant, 'restaurant'), 'category')
        ->for(Kitchen::factory()->for($restaurant, 'restaurant'), 'kitchen')
        ->create($price !== null ? ['price' => $price] : []);
}

test('a visitor can view the public menu of a verified restaurant without logging in', function () {
    $restaurant = Restaurant::factory()
        ->has(RestaurantVerification::factory()->state(['verified' => true]), 'verification')
        ->create(['name_en' => 'Pizza Palace']);
    $dish = storefrontDishFor($restaurant, price: '10.00');

    $this->get("/r/{$restaurant->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/show')
            ->where('restaurant.name_en', 'Pizza Palace')
            ->has('categories', 1)
            ->where('categories.0.id', $dish->category_id)
            ->has('categories.0.dishes', 1)
            ->where('categories.0.dishes.0.id', $dish->id));
});

test('unavailable dishes are excluded from the public menu', function () {
    $restaurant = Restaurant::factory()
        ->has(RestaurantVerification::factory()->state(['verified' => true]), 'verification')
        ->create();
    storefrontDishFor($restaurant);
    Dish::factory()
        ->for(Category::factory()->for($restaurant, 'restaurant'), 'category')
        ->for(Kitchen::factory()->for($restaurant, 'restaurant'), 'kitchen')
        ->create(['is_available' => false]);

    $this->get("/r/{$restaurant->slug}")
        ->assertInertia(fn ($page) => $page
            ->has('categories', 1)
            ->has('categories.0.dishes', 1));
});

test('a restaurants public menu never includes another restaurants dishes', function () {
    $restaurantA = Restaurant::factory()
        ->has(RestaurantVerification::factory()->state(['verified' => true]), 'verification')
        ->create();
    $dishA = storefrontDishFor($restaurantA);

    $restaurantB = Restaurant::factory()
        ->has(RestaurantVerification::factory()->state(['verified' => true]), 'verification')
        ->create();
    storefrontDishFor($restaurantB);

    $this->get("/r/{$restaurantA->slug}")
        ->assertInertia(fn ($page) => $page
            ->has('categories', 1)
            ->has('categories.0.dishes', 1)
            ->where('categories.0.dishes.0.id', $dishA->id));
});

test('a dish nested under a subcategory appears under its parent category', function () {
    $restaurant = Restaurant::factory()
        ->has(RestaurantVerification::factory()->state(['verified' => true]), 'verification')
        ->create();
    $parent = Category::factory()->for($restaurant, 'restaurant')->create();
    $child = Category::factory()->for($restaurant, 'restaurant')->create(['parent_id' => $parent->id]);
    $dish = Dish::factory()
        ->for($child, 'category')
        ->for(Kitchen::factory()->for($restaurant, 'restaurant'), 'kitchen')
        ->create();

    $this->get("/r/{$restaurant->slug}")
        ->assertInertia(fn ($page) => $page
            ->has('categories', 1)
            ->where('categories.0.id', $parent->id)
            ->has('categories.0.children', 1)
            ->where('categories.0.children.0.id', $child->id)
            ->has('categories.0.children.0.dishes', 1)
            ->where('categories.0.children.0.dishes.0.id', $dish->id));
});

test('a category with no dishes anywhere in its subtree is omitted from the public menu', function () {
    $restaurant = Restaurant::factory()
        ->has(RestaurantVerification::factory()->state(['verified' => true]), 'verification')
        ->create();
    Category::factory()->for($restaurant, 'restaurant')->create();

    $this->get("/r/{$restaurant->slug}")
        ->assertInertia(fn ($page) => $page->has('categories', 0));
});

test('an inactive category is excluded from the public menu even with available dishes', function () {
    $restaurant = Restaurant::factory()
        ->has(RestaurantVerification::factory()->state(['verified' => true]), 'verification')
        ->create();
    $category = Category::factory()->for($restaurant, 'restaurant')->create(['is_active' => false]);
    Dish::factory()
        ->for($category, 'category')
        ->for(Kitchen::factory()->for($restaurant, 'restaurant'), 'kitchen')
        ->create();

    $this->get("/r/{$restaurant->slug}")
        ->assertInertia(fn ($page) => $page->has('categories', 0));
});

test('a restaurant explicitly marked unverified has no public menu', function () {
    $restaurant = Restaurant::factory()
        ->has(RestaurantVerification::factory()->state(['verified' => false]), 'verification')
        ->create();

    $this->get("/r/{$restaurant->slug}")->assertNotFound();
});

test('a restaurant with no verification record at all has no public menu', function () {
    $restaurant = Restaurant::factory()->create();

    $this->get("/r/{$restaurant->slug}")->assertNotFound();
});

test('an unknown storefront slug returns not found', function () {
    $this->get('/r/does-not-exist')->assertNotFound();
});
