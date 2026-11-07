<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\CustomReviewAttempt;
use App\Models\CustomReviewAttemptItem;
use App\Support\ReviewSectionBoard;

/**
 * An attempt's steps, read from the snapshots taken when it started.
 *
 * The items never look at the live word, question or session: the stored card
 * is exactly what the attempt showed, so an edit made after the attempt cannot
 * rewrite it. Grouping, progress and navigation come from the same board Review
 * uses, so the two screens behave alike.
 *
 * @phpstan-import-type Section from ReviewSectionBoard
 */
final readonly class AttemptSections
{
    public function __construct(private ReviewSectionBoard $board) {}

    /**
     * @return list<Section>
     */
    public function for(CustomReviewAttempt $attempt): array
    {
        $items = [];

        foreach ($attempt->items as $item) {
            $items[] = [
                'position' => $item->position,
                'kind' => $item->kind,
                'identity' => $item->item_identity,
                'exercise' => $item->exercise,
                'card' => $item->snapshot,
                'rating' => $item->rating?->value,
                'skipped' => $item->isSkipped(),
            ];
        }

        return $this->board->build(
            $items,
            fn (string $exercise, int $position): string => route('customReviews.attempts.show', [
                'customReview' => $attempt->custom_review_id,
                'attempt' => $attempt->id,
                'step' => $exercise,
                'position' => $position,
            ]),
        );
    }

    /**
     * The derived tallies the finish summary and the history show. Counts are
     * read from the ratings every time and never stored.
     *
     * @param  iterable<int, CustomReviewAttemptItem>  $items
     * @return array{hard: int, okay: int, easy: int, skipped: int, rated: int, unrated: int}
     */
    public function counts(iterable $items): array
    {
        $counts = ['hard' => 0, 'okay' => 0, 'easy' => 0, 'skipped' => 0, 'rated' => 0, 'unrated' => 0];

        foreach ($items as $item) {
            if ($item->rating !== null) {
                $counts['rated'] += 1;
                $counts[$item->rating->value] += 1;

                continue;
            }

            if ($item->isSkipped()) {
                $counts['skipped'] += 1;

                continue;
            }

            $counts['unrated'] += 1;
        }

        return $counts;
    }
}
