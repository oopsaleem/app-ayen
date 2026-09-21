<?php

use App\Models\Company;
use App\Models\Manager;
use App\Models\User;

test('a user becomes a manager by having a manager row', function () {
    $user = User::factory()->create();

    expect($user->isManager())->toBeFalse();

    Manager::factory()->create(['user_id' => $user->id]);

    expect($user->fresh()->isManager())->toBeTrue();
});

test('a manager can be unassigned from any company', function () {
    $manager = Manager::factory()->create(['company_id' => null]);

    expect($manager->company_id)->toBeNull()
        ->and($manager->company)->toBeNull();
});

test('a manager belongs to exactly one company when assigned', function () {
    $company = Company::factory()->create();
    $manager = Manager::factory()->create(['company_id' => $company->id]);

    expect($manager->company->id)->toBe($company->id);
});
