<?php

use App\Models\DeliveryAddress;
use App\Models\User;

test('a customer sees only their own addresses on the index', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    DeliveryAddress::factory()->create(['user_id' => $user->id, 'caption' => 'Home']);
    DeliveryAddress::factory()->create(['user_id' => $other->id, 'caption' => 'Not mine']);

    $this->actingAs($user)->get(route('addresses.index'))
        ->assertInertia(fn ($page) => $page
            ->component('addresses/index')
            ->has('addresses', 1)
            ->where('addresses.0.caption', 'Home'));
});

test('guests cannot view the addresses index', function () {
    $this->get(route('addresses.index'))->assertRedirect(route('login'));
});

test('a customer can create a delivery address', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('addresses.store'), [
        'caption' => 'Home',
        'address' => '123 Main St',
        'lat' => 15.3547,
        'lng' => 44.2066,
        'is_default' => true,
    ]);

    $response->assertRedirect(route('addresses.index'));
    $this->assertDatabaseHas('delivery_addresses', [
        'user_id' => $user->id,
        'caption' => 'Home',
        'address' => '123 Main St',
        'is_default' => true,
    ]);
});

test('delivery address fields are required and lat lng are range checked', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('addresses.store'), ['lat' => 91, 'lng' => -181])
        ->assertInvalid(['caption', 'address', 'lat', 'lng']);
});

test('setting a new address as the default unsets the previous default', function () {
    $user = User::factory()->create();
    DeliveryAddress::factory()->create(['user_id' => $user->id, 'caption' => 'Old default', 'is_default' => true]);

    $this->actingAs($user)->post(route('addresses.store'), [
        'caption' => 'New default',
        'address' => '456 Side St',
        'lat' => 15.0,
        'lng' => 44.0,
        'is_default' => true,
    ]);

    $fresh = $user->fresh()->deliveryAddresses->keyBy('caption');

    expect($fresh['New default']->is_default)->toBeTrue()
        ->and($fresh['Old default']->is_default)->toBeFalse();
});

test('a customer can update their own address', function () {
    $user = User::factory()->create();
    $address = DeliveryAddress::factory()->create(['user_id' => $user->id, 'caption' => 'Home']);

    $this->actingAs($user)->get(route('addresses.edit', $address))
        ->assertInertia(fn ($page) => $page
            ->component('addresses/edit')
            ->where('deliveryAddress.caption', 'Home'));

    $response = $this->actingAs($user)->patch(route('addresses.update', $address), [
        'caption' => 'Work',
        'address' => $address->address,
        'lat' => 15.0,
        'lng' => 44.0,
        'is_default' => true,
    ]);

    $response->assertRedirect(route('addresses.index'));
    expect($address->fresh()->caption)->toBe('Work')
        ->and($address->fresh()->is_default)->toBeTrue();
});

test('updating an address as the default unsets the other default', function () {
    $user = User::factory()->create();
    $address = DeliveryAddress::factory()->create(['user_id' => $user->id, 'caption' => 'Mine']);
    DeliveryAddress::factory()->create(['user_id' => $user->id, 'caption' => 'Other', 'is_default' => true]);

    $this->actingAs($user)->patch(route('addresses.update', $address), [
        'caption' => 'Mine',
        'address' => $address->address,
        'lat' => 15.0,
        'lng' => 44.0,
        'is_default' => true,
    ]);

    $fresh = $user->fresh()->deliveryAddresses->keyBy('caption');

    expect($fresh['Mine']->is_default)->toBeTrue()
        ->and($fresh['Other']->is_default)->toBeFalse();
});

test('a customer can delete their own address', function () {
    $user = User::factory()->create();
    $address = DeliveryAddress::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->delete(route('addresses.destroy', $address))
        ->assertRedirect(route('addresses.index'));

    $this->assertDatabaseMissing('delivery_addresses', ['id' => $address->id]);
});

test('another customer cannot view edit or delete someone elses address', function () {
    $owner = User::factory()->create();
    $address = DeliveryAddress::factory()->create(['user_id' => $owner->id, 'caption' => 'Home']);
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->get(route('addresses.edit', $address))->assertForbidden();
    $this->actingAs($intruder)->patch(route('addresses.update', $address), [
        'caption' => 'Hacked',
        'address' => 'Nowhere',
        'lat' => 0,
        'lng' => 0,
    ])->assertForbidden();
    $this->actingAs($intruder)->delete(route('addresses.destroy', $address))->assertForbidden();

    expect($address->fresh()->caption)->toBe('Home');
});
