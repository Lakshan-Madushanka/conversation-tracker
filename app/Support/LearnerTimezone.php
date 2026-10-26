<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The learner's time zone, normalised once. An unknown or missing value falls
 * back to the application's zone, so a hand-made address never fails a page.
 */
final class LearnerTimezone
{
    public static function normalise(mixed $value): string
    {
        if (is_string($value) && self::isKnown($value)) {
            return $value;
        }

        $fallback = config('app.timezone');

        return is_string($fallback) && self::isKnown($fallback)
            ? $fallback
            : 'UTC';
    }

    private static function isKnown(string $value): bool
    {
        return in_array($value, timezone_identifiers_list(), true);
    }
}
