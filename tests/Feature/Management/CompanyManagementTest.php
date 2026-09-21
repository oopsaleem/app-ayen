<?php

use App\Models\Admin;
use App\Models\Company;
use App\Models\Manager;
use App\Models\User;

test('admins can view the companies index', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    Company::factory()->count(2)->create();

    $response = $this->actingAs($admin)->get(route('companies.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('companies/index')
        ->has('companies', 2));
});

test('non admins cannot view the companies index', function () {
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id]);

    $this->actingAs($manager)->get(route('companies.index'))->assertForbidden();
});

test('admins can create a company', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $response = $this->actingAs($admin)->post(route('companies.store'), [
        'display_name' => 'Yemen Oasis Group',
        'description' => 'A family of Yemeni restaurants.',
    ]);

    $company = Company::firstWhere('display_name', 'Yemen Oasis Group');
    $response->assertRedirect(route('companies.edit', $company));
    $this->assertDatabaseHas('companies', ['display_name' => 'Yemen Oasis Group']);
});

test('company display name is required', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->post(route('companies.store'), ['display_name' => ''])
        ->assertInvalid(['display_name']);
});

test('admins can update a company', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    $company = Company::factory()->create(['display_name' => 'Old Name']);

    $response = $this->actingAs($admin)->patch(route('companies.update', $company), [
        'display_name' => 'New Name',
        'description' => $company->description,
    ]);

    $response->assertRedirect(route('companies.edit', $company));
    expect($company->fresh()->display_name)->toBe('New Name');
});

test('a manager can view and update their own companys edit page', function () {
    $company = Company::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $company->id]);

    $this->actingAs($manager)->get(route('companies.edit', $company))
        ->assertInertia(fn ($page) => $page->component('companies/edit'));

    $this->actingAs($manager)->patch(route('companies.update', $company), [
        'display_name' => 'Manager Renamed',
        'description' => null,
    ])->assertRedirect(route('companies.edit', $company));
});
