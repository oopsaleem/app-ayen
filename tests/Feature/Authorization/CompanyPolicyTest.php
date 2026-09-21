<?php

use App\Models\Admin;
use App\Models\Company;
use App\Models\Manager;
use App\Models\User;

test('an admin can view and update any company', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    $company = Company::factory()->create();

    expect($admin->can('view', $company))->toBeTrue()
        ->and($admin->can('update', $company))->toBeTrue();
});

test('a manager can view and update only their own company', function () {
    $ownCompany = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    $user = User::factory()->create();
    Manager::factory()->create(['user_id' => $user->id, 'company_id' => $ownCompany->id]);

    expect($user->can('view', $ownCompany))->toBeTrue()
        ->and($user->can('update', $ownCompany))->toBeTrue()
        ->and($user->can('view', $otherCompany))->toBeFalse()
        ->and($user->can('update', $otherCompany))->toBeFalse();
});

test('a user with no admin or manager role cannot view or update a company', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();

    expect($user->can('view', $company))->toBeFalse()
        ->and($user->can('update', $company))->toBeFalse();
});

test('an unassigned manager cannot view or update any company', function () {
    $user = User::factory()->create();
    Manager::factory()->create(['user_id' => $user->id, 'company_id' => null]);
    $company = Company::factory()->create();

    expect($user->can('view', $company))->toBeFalse()
        ->and($user->can('update', $company))->toBeFalse();
});

test('only admins can create companies', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id]);

    expect($admin->can('create', Company::class))->toBeTrue()
        ->and($manager->can('create', Company::class))->toBeFalse();
});

test('only admins can delete companies', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $company->id]);

    expect($admin->can('delete', $company))->toBeTrue()
        ->and($manager->can('delete', $company))->toBeFalse();
});
