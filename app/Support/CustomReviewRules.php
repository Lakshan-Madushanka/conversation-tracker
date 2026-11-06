<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The recommended size of a custom review, and when crossing it is worth a
 * word.
 *
 * Twenty items is roughly a quarter of an hour of practice. It is advice, not
 * a rule: no add is refused and no review has a maximum, so the only thing
 * this decides is whether to explain the size first.
 *
 * The same number appears in `resources/js/lib/custom-review.ts`. The server
 * is the one that asks, so the client never decides on its own.
 */
final readonly class CustomReviewRules
{
    /** How many items a review is recommended to hold. */
    public const int RECOMMENDED_ITEMS = 20;

    /**
     * Whether an add that ends with the given number of items should be
     * explained first.
     *
     * An add that only repeats what is already there changes nothing and never
     * asks. One that lands past the recommendation asks every time, even on a
     * review that is already past it.
     */
    public static function needsConfirmation(int $resultingCount, int $addedCount): bool
    {
        return $addedCount > 0 && $resultingCount > self::RECOMMENDED_ITEMS;
    }
}
