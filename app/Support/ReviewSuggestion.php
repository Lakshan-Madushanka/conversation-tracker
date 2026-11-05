<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\FeedbackType;

/**
 * The result of choosing today's items: the fixed list, the count per
 * exercise kind, and the honest estimate and mix built from it. The card on
 * screen and the review that starts both read this, so they always agree.
 */
final readonly class ReviewSuggestion
{
    /**
     * @param  list<array{kind: FeedbackType, identity: string, exercise: string}>  $items
     *                                                                                      the items to review, in the order the learner meets them
     */
    public function __construct(public array $items) {}

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * How many items of each exercise kind are in the review. The exercise is
     * the one chosen for this sitting, so a word counted as a blank is not
     * also counted as a flashcard.
     *
     * @return array<string, int>
     */
    public function perKind(): array
    {
        $counts = [];

        foreach ($this->items as $item) {
            $counts[$item['exercise']] = ($counts[$item['exercise']] ?? 0) + 1;
        }

        return $counts;
    }

    /** Whole minutes for the review, at least one. */
    public function minutes(): int
    {
        return ReviewExercises::minutes($this->perKind());
    }

    /** The mix description, with correct singular and plural wording. */
    public function mix(): string
    {
        return ReviewExercises::describe($this->perKind());
    }
}
