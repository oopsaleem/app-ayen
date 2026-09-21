<?php

use App\Models\Admin;
use App\Models\Company;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\RestaurantAddress;
use App\Models\User;

test('admins see every restaurant on the index', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    Restaurant::factory()->count(3)->create();

    $this->actingAs($admin)->get(route('restaurants.index'))
        ->assertInertia(fn ($page) => $page->component('restaurants/index')->has('restaurants', 3));
});

test('managers only see their own companys restaurants on the index', function () {
    $company = Company::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $company->id]);
    Restaurant::factory()->count(2)->create(['company_id' => $company->id]);
    Restaurant::factory()->create();

    $this->actingAs($manager)->get(route('restaurants.index'))
        ->assertInertia(fn ($page) => $page->component('restaurants/index')->has('restaurants', 2));
});

test('a manager can create a restaurant with an address under their company', function () {
    $company = Company::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $company->id]);

    $response = $this->actingAs($manager)->post(route('restaurants.store', $company), [
        'name_en' => 'Yemen Oasis',
        'name_ar' => 'واحة اليمن',
        'description_en' => null,
        'description_ar' => null,
        'address' => [
            'address' => '123 Main St',
            'lat' => 15.3547,
            'lng' => 44.2066,
        ],
    ]);

    $restaurant = Restaurant::firstWhere('name_en', 'Yemen Oasis');
    $response->assertRedirect(route('restaurants.edit', $restaurant));
    $this->assertDatabaseHas('restaurants', ['name_en' => 'Yemen Oasis', 'company_id' => $company->id]);
    $this->assertDatabaseHas('restaurant_addresses', ['restaurant_id' => $restaurant->id, 'address' => '123 Main St']);
});

test('a manager from a different company cannot create a restaurant under this company', function () {
    $company = Company::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id]);

    $this->actingAs($manager)->post(route('restaurants.store', $company), [
        'name_en' => 'Yemen Oasis',
        'name_ar' => 'واحة اليمن',
        'address' => ['address' => '123 Main St', 'lat' => 15.3547, 'lng' => 44.2066],
    ])->assertForbidden();
});

test('a manager from another company cannot view a restaurants edit page', function () {
    $restaurant = Restaurant::factory()->create();
    $otherManager = User::factory()->create();
    Manager::factory()->create(['user_id' => $otherManager->id]);

    $this->actingAs($otherManager)->get(route('restaurants.edit', $restaurant))
        ->assertForbidden();
});

test('updating a restaurant also updates its address', function () {
    $restaurant = Restaurant::factory()
        ->has(RestaurantAddress::factory(), 'address')
        ->create();
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->patch(route('restaurants.update', $restaurant), [
        'name_en' => $restaurant->name_en,
        'name_ar' => $restaurant->name_ar,
        'address' => ['address' => 'New address', 'lat' => 1.0, 'lng' => 2.0],
    ])->assertRedirect(route('restaurants.edit', $restaurant));

    $this->assertDatabaseHas('restaurant_addresses', ['restaurant_id' => $restaurant->id, 'address' => 'New address']);
});
