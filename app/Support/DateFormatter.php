<?php

namespace App\Support;

use Carbon\CarbonInterface;

class DateFormatter
{
    /**
     * Format a date using the current locale's human-readable pattern.
     *
     * Machine-readable formats (ISO dates, XLSX values) are never localized.
     */
    public static function format(?CarbonInterface $date, bool $withTime = false): ?string
    {
        if ($date === null) {
            return null;
        }

        $key = $withTime ? 'datetime_format' : 'date_format';
        $formats = (array) config("locales.{$key}", []);
        $fallback = (string) config('locales.fallback', 'en');

        $pattern = $formats[app()->getLocale()]
            ?? $formats[$fallback]
            ?? ($withTime ? 'd.m.Y H:i' : 'd.m.Y');

        return $date->format($pattern);
    }
}
