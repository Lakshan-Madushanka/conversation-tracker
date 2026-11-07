<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A count as the learner reads it: 1st, 2nd, 3rd, 4th, 11th, 21st.
 *
 * Used for attempt numbers, so the screen and the history word them the same
 * way.
 */
final class Ordinal
{
    public static function of(int $number): string
    {
        $lastTwo = $number % 100;

        if ($lastTwo >= 11 && $lastTwo <= 13) {
            return $number.'th';
        }

        return $number.match ($number % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}
