<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The order the Custom reviews listing can be read in.
 *
 * Most recently attempted is the default because a review the learner has just
 * practised is usually the one they want to find again. Sorting never counts
 * as a filter, so it is never shown as a chip and Clear all leaves it alone.
 */
enum CustomReviewSort: string
{
    case RecentlyAttempted = 'recently_attempted';

    case Name = 'name';

    case Created = 'created';

    /** The words the learner sees. */
    public function label(): string
    {
        return match ($this) {
            self::RecentlyAttempted => 'Most recently attempted',
            self::Name => 'Name (A to Z)',
            self::Created => 'Created (newest first)',
        };
    }

    /** The order the sort control lists them in. */
    public static function default(): self
    {
        return self::RecentlyAttempted;
    }

    /** Read a sort from the address, falling back quietly to the default. */
    public static function fromInput(mixed $value): self
    {
        return is_string($value)
            ? self::tryFrom($value) ?? self::default()
            : self::default();
    }

    /**
     * Every order, in the order the control lists them.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::RecentlyAttempted, self::Name, self::Created];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $sort): string => $sort->value, self::ordered());
    }
}
