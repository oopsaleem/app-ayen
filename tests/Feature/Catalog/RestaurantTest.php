<?php

use App\Models\Company;
use App\Models\Restaurant;

test('a restaurant belongs to a company', function () {
    $company = Company::factory()->create();
    $restaurant = Restaurant::factory()->create(['company_id' => $company->id]);

    expect($restaurant->company->id)->toBe($company->id);
});

test('a restaurant stores bilingual name and description', function () {
    $restaurant = Restaurant::factory()->create([
        'name_en' => 'Yemen Oasis',
        'name_ar' => 'واحة اليمن',
        'description_en' => 'Traditional Yemeni cuisine.',
        'description_ar' => 'مأكولات يمنية تقليدية.',
    ]);

    app()->setLocale('en');
    expect($restaurant->localized('name'))->toBe('Yemen Oasis');

    app()->setLocale('ar');
    expect($restaurant->localized('name'))->toBe('واحة اليمن');
});

test('a restaurant stores a single set of images, not light/dark variants', function () {
    $restaurant = Restaurant::factory()->create([
        'images' => ['https://example.test/one.jpg', 'https://example.test/two.jpg'],
    ]);

    expect($restaurant->fresh()->images)->toBe([
        'https://example.test/one.jpg',
        'https://example.test/two.jpg',
    ]);
});
