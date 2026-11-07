<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Models\Rating;
use App\Models\Review;
use App\Support\ReviewExercises;
use App\Support\ReviewSectionBoard;

/**
 * The items of a review grouped into one section per exercise kind, in the
 * fixed friendly order the stepper shows.
 *
 * A section remembers every item it holds and the rating each one already has,
 * so the screen can open any step at any time and always return to the next
 * item still to rate there. The cards are read from the current session data
 * in one batch, so an edit shows straight away and an item that was removed or
 * archived is skipped.
 *
 * Only the source is review-specific: grouping, progress and navigation are
 * shared with an attempt through `ReviewSectionBoard`.
 *
 * @phpstan-import-type Section from ReviewSectionBoard
 * @phpstan-import-type SectionItem from ReviewSectionBoard
 */
final readonly class ReviewSections
{
    public function __construct(
        private ReviewCandidates $candidates,
        private ReviewSectionBoard $board,
    ) {}

    /**
     * @return list<Section>
     */
    public function for(Review $review): array
    {
        /** @var list<array{kind: FeedbackType, identity: string, exercise: string}> $pairs */
        $pairs = [];

        foreach ($review->items as $item) {
            $pairs[] = [
                'kind' => $item->kind,
                'identity' => $item->item_identity,
                'exercise' => $item->exercise ?? ReviewExercises::kindOf($item->kind),
            ];
        }

        $payloads = $this->candidates->payloadsFor($pairs);
        $ratings = $this->ratingsFor($review);

        $items = [];

        foreach ($review->items as $index => $item) {
            $pair = $pairs[$index];
            $key = $this->candidates->key($item->kind, $item->item_identity);
            $payload = $payloads[$key] ?? null;

            // A removed, archived or changed item is skipped; the review moves on.
            if ($payload === null) {
                continue;
            }

            $items[] = [
                'position' => $item->position,
                'kind' => $item->kind,
                'identity' => $item->item_identity,
                'exercise' => $pair['exercise'],
                'card' => $payload,
                'rating' => ($ratings[$key] ?? null)?->rating->value,
            ];
        }

        return $this->board->build(
            $items,
            fn (string $exercise, int $position): string => route('review.session', [
                'review' => $review->id,
                'step' => $exercise,
                'position' => $position,
            ]),
        );
    }

    /**
     * @return array<string, Rating> keyed by kind and identity
     */
    private function ratingsFor(Review $review): array
    {
        $found = [];

        foreach ($review->ratings as $rating) {
            $found[$this->candidates->key($rating->kind, $rating->item_identity)] = $rating;
        }

        return $found;
    }
}
