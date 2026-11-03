<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How the learner judged one reviewed item: how much more practice it needs.
 *
 * The stored value is what the buttons send and what the future schedule will
 * read, so the wording of a rating never leaks into the database.
 */
enum ReviewRating: string
{
    case Hard = 'hard';

    case Okay = 'okay';

    case Easy = 'easy';

    /** The short word shown to the learner. */
    public function label(): string
    {
        return match ($this) {
            self::Hard => 'Hard',
            self::Okay => 'Okay',
            self::Easy => 'Easy',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $rating): string => $rating->value, self::cases());
    }
}
