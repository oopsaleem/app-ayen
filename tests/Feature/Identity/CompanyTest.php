<?php

use App\Models\Company;

test('a company can be created with a display name and description', function () {
    $company = Company::factory()->create([
        'display_name' => 'Yemen Oasis Group',
        'description' => 'A family of Yemeni restaurants.',
    ]);

    expect($company->display_name)->toBe('Yemen Oasis Group')
        ->and($company->description)->toBe('A family of Yemeni restaurants.');

    $this->assertDatabaseHas('companies', [
        'id' => $company->id,
        'display_name' => 'Yemen Oasis Group',
    ]);
});
