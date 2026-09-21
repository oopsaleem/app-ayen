<?php

namespace App\Support;

class Locale
{
    /**
     * Get the platform's configured secondary (non-English) locale code.
     *
     * Never hardcode 'ar' when deciding which field holds the secondary
     * translation — always go through this helper (see ADR-0006).
     */
    public static function secondary(): string
    {
        $locales = array_keys(config('app.available_locales', []));

        $secondary = collect($locales)->first(fn (string $locale): bool => $locale !== 'en');

        return $secondary ?? 'ar';
    }
}
