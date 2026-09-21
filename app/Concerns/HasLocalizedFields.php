<?php

namespace App\Concerns;

use App\Support\Locale;

trait HasLocalizedFields
{
    /**
     * Get the value of a bilingual field for the current app locale,
     * falling back to the English column when no translation exists.
     */
    public function localized(string $field): ?string
    {
        $suffix = app()->getLocale() === Locale::secondary() ? Locale::secondary() : 'en';

        $value = $this->{"{$field}_{$suffix}"} ?? null;

        if ($value !== null && $value !== '') {
            return $value;
        }

        return $this->{"{$field}_en"};
    }
}
