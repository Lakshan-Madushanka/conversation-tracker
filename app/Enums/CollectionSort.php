<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The orders a collection can be shown in.
 *
 * Every collection starts in the order that suits it: mistakes by how often
 * they were seen, the rest by their newest session. Sorting is a view choice,
 * never a filter, so it is not shown as a removable chip.
 */
enum CollectionSort: string
{
    case Newest = 'newest';

    case Alphabetical = 'az';

    case MostSeen = 'most_seen';

    /** The order a tab uses before the learner chooses one. */
    public static function defaultFor(FeedbackType $type): self
    {
        return $type === FeedbackType::Mistake
            ? self::MostSeen
            : self::Newest;
    }

    /**
     * The value the list can honour for one collection. Grammar notes are
     * never seen more than once, so "most often seen" would say nothing.
     */
    public static function fromQueryFor(?string $value, FeedbackType $type): self
    {
        $sort = self::tryFrom($value ?? '');

        if ($sort === null || ! $sort->availableFor($type)) {
            return self::defaultFor($type);
        }

        return $sort;
    }

    public function availableFor(FeedbackType $type): bool
    {
        return $this !== self::MostSeen || $type !== FeedbackType::GrammarNote;
    }

    public function label(): string
    {
        return match ($this) {
            self::Newest => 'Newest first',
            self::Alphabetical => 'A to Z',
            self::MostSeen => 'Most often seen',
        };
    }
}
