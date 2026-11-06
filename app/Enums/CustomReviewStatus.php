<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a custom review stands: nothing in it yet, still being built, or ready
 * to practise.
 *
 * `Empty` follows the item count and `Draft` is what a review becomes when its
 * first item arrives, so both are the app's own moves. `Finished` is chosen
 * only by the learner, and Step 2 is the only code that writes it.
 *
 * This says nothing about an attempt. A review marked `Finished` is ready to
 * practise; an attempt `Finished early` ended before every item was rated.
 */
enum CustomReviewStatus: string
{
    case Empty = 'empty';

    case Draft = 'draft';

    case Finished = 'finished';

    /** The words the learner sees. */
    public function label(): string
    {
        return match ($this) {
            self::Empty => 'Empty',
            self::Draft => 'Draft',
            self::Finished => 'Finished',
        };
    }

    /** Whether the learner is still building a review in this status. */
    public function isBeingBuilt(): bool
    {
        return $this !== self::Finished;
    }

    /**
     * Every status, in the order a filter lists them.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::Empty, self::Draft, self::Finished];
    }

    /**
     * The statuses a review the learner is still building can hold.
     *
     * @return list<self>
     */
    public static function beingBuilt(): array
    {
        return [self::Empty, self::Draft];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::ordered());
    }

    /**
     * @return list<string>
     */
    public static function beingBuiltValues(): array
    {
        return array_map(fn (self $status): string => $status->value, self::beingBuilt());
    }
}
