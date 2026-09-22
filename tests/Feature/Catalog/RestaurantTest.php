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

test('a restaurant is created with a slug derived from its english name', function () {
    $restaurant = Restaurant::factory()->create(['name_en' => 'Pizza Palace']);

    expect($restaurant->slug)->toBe('pizza-palace');
});

test('a restaurant slug uses the next available suffix on collision', function () {
    Restaurant::factory()->create(['name_en' => 'Pizza Palace', 'slug' => 'pizza-palace']);
    Restaurant::factory()->create(['name_en' => 'Pizza Palace', 'slug' => 'pizza-palace-1']);

    $restaurant = Restaurant::factory()->create(['name_en' => 'Pizza Palace']);

    expect($restaurant->slug)->toBe('pizza-palace-2');
});

test('renaming a restaurant regenerates its slug', function () {
    $restaurant = Restaurant::factory()->create(['name_en' => 'Pizza Palace']);

    $restaurant->update(['name_en' => 'Pasta Place']);

    expect($restaurant->fresh()->slug)->toBe('pasta-place');
});

test('updating a restaurant without changing its name keeps the existing slug', function () {
    $restaurant = Restaurant::factory()->create(['name_en' => 'Pizza Palace']);

    $restaurant->update(['description_en' => 'Updated description']);

    expect($restaurant->fresh()->slug)->toBe('pizza-palace');
});

test('a restaurant name that slugifies to an empty string falls back to a default slug', function () {
    $restaurant = Restaurant::factory()->create(['name_en' => '###']);

    expect($restaurant->slug)->toBe('restaurant');
});
