<?php

use App\Support\Locale;

test('secondary locale is derived from config, defaulting to ar today', function () {
    expect(Locale::secondary())->toBe('ar');
});

test('secondary locale falls back to ar when config has only english', function () {
    config(['app.available_locales' => ['en' => 'English']]);

    expect(Locale::secondary())->toBe('ar');
});

test('secondary locale reflects whatever non-english locale is configured', function () {
    config(['app.available_locales' => ['en' => 'English', 'fr' => 'Français']]);

    expect(Locale::secondary())->toBe('fr');
});
