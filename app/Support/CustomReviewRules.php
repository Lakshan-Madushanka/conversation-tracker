<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The size of a custom review: the recommendation spoken of, and the maximum
 * enforced.
 *
 * Twenty items is roughly a quarter of an hour of practice. It is advice, not
 * a rule: the recommendation only decides whether to explain the size first.
 * Fifty is the hard ceiling: an add that would cross it is refused as a whole,
 * with the room left named.
 *
 * The same numbers appear in `resources/js/lib/custom-review.ts`. The server
 * is the one that asks and refuses, so the client never decides on its own.
 */
final readonly class CustomReviewRules
{
    /** How many items a review is recommended to hold. */
    public const int RECOMMENDED_ITEMS = 20;

    /** How many items a review may hold, counting every kind together. */
    public const int MAX_ITEMS = 50;

    /** The longest name the learner may give a review. */
    public const int NAME_MAX_LENGTH = 80;

    /** The longest description a review may carry. */
    public const int DESCRIPTION_MAX_LENGTH = 500;

    /** Whether a review holding this many items carries the size tag. */
    public static function isTooMany(int $itemCount): bool
    {
        return $itemCount > self::RECOMMENDED_ITEMS;
    }

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

    /** Whether an add ending with this many items crosses the hard maximum. */
    public static function crossesMaximum(int $resultingCount): bool
    {
        return $resultingCount > self::MAX_ITEMS;
    }

    /** What a refused add says, naming the room the review has left. */
    public static function maximumMessage(int $heldCount): string
    {
        $room = self::MAX_ITEMS - $heldCount;

        if ($room === 0) {
            return 'This review is full at '.self::MAX_ITEMS.' items.';
        }

        return 'This review has room for '.$room.' more '.($room === 1 ? 'item' : 'items').'.';
    }
}
