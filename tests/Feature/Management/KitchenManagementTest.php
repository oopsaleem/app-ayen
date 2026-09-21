<?php

use App\Models\Admin;
use App\Models\Kitchen;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\User;

test('a manager can create a kitchen under their restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $response = $this->actingAs($manager)->post(route('kitchens.store', $restaurant), [
        'name_en' => 'Grill',
        'name_ar' => 'شواية',
    ]);

    $kitchen = Kitchen::firstWhere('name_en', 'Grill');
    $response->assertRedirect(route('restaurants.edit', $restaurant));
    $this->assertDatabaseHas('kitchens', ['restaurant_id' => $restaurant->id, 'name_en' => 'Grill']);
});

test('a manager from a different company cannot create a kitchen here', function () {
    $restaurant = Restaurant::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id]);

    $this->actingAs($manager)->post(route('kitchens.store', $restaurant), [
        'name_en' => 'Grill',
        'name_ar' => 'شواية',
    ])->assertForbidden();
});

test('an admin can edit and update a kitchen', function () {
    $kitchen = Kitchen::factory()->create(['name_en' => 'Old']);
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->get(route('kitchens.edit', $kitchen))
        ->assertInertia(fn ($page) => $page->component('kitchens/edit'));

    $this->actingAs($admin)->patch(route('kitchens.update', $kitchen), [
        'name_en' => 'New',
        'name_ar' => $kitchen->name_ar,
    ])->assertRedirect(route('restaurants.edit', $kitchen->restaurant));

    expect($kitchen->fresh()->name_en)->toBe('New');
});

test('the restaurant edit page lists its kitchens', function () {
    $restaurant = Restaurant::factory()->create();
    Kitchen::factory()->create(['restaurant_id' => $restaurant->id, 'name_en' => 'Grill']);
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->get(route('restaurants.edit', $restaurant))
        ->assertInertia(fn ($page) => $page
            ->component('restaurants/edit')
            ->where('kitchens.0.name_en', 'Grill'));
});
