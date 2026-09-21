<?php

use App\Models\Admin;
use App\Models\Kitchen;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\User;

test('admins and the owning companys manager can manage kitchens', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->create(['restaurant_id' => $restaurant->id]);
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);
    $otherManager = User::factory()->create();
    Manager::factory()->create(['user_id' => $otherManager->id]);

    expect($admin->can('view', $kitchen))->toBeTrue()
        ->and($admin->can('update', $kitchen))->toBeTrue()
        ->and($admin->can('delete', $kitchen))->toBeTrue()
        ->and($manager->can('create', [Kitchen::class, $restaurant]))->toBeTrue()
        ->and($manager->can('update', $kitchen))->toBeTrue()
        ->and($otherManager->can('update', $kitchen))->toBeFalse()
        ->and($otherManager->can('create', [Kitchen::class, $restaurant]))->toBeFalse();
});
