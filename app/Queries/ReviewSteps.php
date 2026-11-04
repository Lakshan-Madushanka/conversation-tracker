<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Http\Resources\ReviewCardResource;
use App\Models\PracticeFeedbackEntry;
use App\Models\Rating;
use App\Models\Review;
use App\Models\ReviewItem;
use Illuminate\Support\Collection;

/**
 * The items of a review as the stepper shows them: one step per item, in the
 * order the learner meets them, with the card and its rating already attached.
 *
 * The cards are read from the current session data in one batch, so an edit
 * shows straight away and an item that was removed or archived is left out.
 * Every step carries its own address, so the learner can open any step at any
 * time instead of having to finish the one before it.
 *
 * @phpstan-type Step array{
 *     number: int,
 *     card: array<string, mixed>,
 *     rating: string|null,
 *     url: string
 * }
 */
final readonly class ReviewSteps
{
    public function __construct(private ReviewCandidates $candidates) {}

    /**
     * @return list<Step>
     */
    public function for(Review $review): array
    {
        $items = $review->items;
        $entries = $this->entriesFor($items);
        $ratings = $this->ratingsFor($review);

        $steps = [];

        foreach ($items as $item) {
            $key = $this->candidates->key($item->kind, $item->item_identity);
            $entry = $entries[$key] ?? null;

            // A removed or archived item is skipped; the review moves on.
            if (! $entry instanceof PracticeFeedbackEntry) {
                continue;
            }

            $number = count($steps) + 1;

            $steps[] = [
                'number' => $number,
                'card' => (new ReviewCardResource($entry))->resolve(),
                'rating' => ($ratings[$key] ?? null)?->rating->value,
                'url' => route('review.session', ['review' => $review->id, 'step' => $number]),
            ];
        }

        return $steps;
    }

    /**
     * The step the address names, or the first one still to rate.
     *
     * @param  list<Step>  $steps
     * @return Step|null
     */
    public function current(array $steps, mixed $requested): ?array
    {
        if ($steps === []) {
            return null;
        }

        if (is_numeric($requested)) {
            foreach ($steps as $step) {
                if ($step['number'] === (int) $requested) {
                    return $step;
                }
            }
        }

        foreach ($steps as $step) {
            if ($step['rating'] === null) {
                return $step;
            }
        }

        return $steps[0];
    }

    /**
     * The step number for one item, or null when it is no longer shown.
     *
     * @param  list<Step>  $steps
     */
    public function numberOf(
        array $steps,
        FeedbackType $kind,
        string $itemIdentity,
    ): ?int {
        foreach ($steps as $step) {
            if ($this->matches($step, $kind, $itemIdentity)) {
                return $step['number'];
            }
        }

        return null;
    }

    /**
     * The next step still to rate after the one just rated, wrapping around
     * to the start. Null when every step has a rating.
     *
     * @param  list<Step>  $steps
     */
    public function nextUnrated(
        array $steps,
        FeedbackType $kind,
        string $itemIdentity,
    ): ?int {
        $rated = null;

        foreach ($steps as $index => $step) {
            if ($this->matches($step, $kind, $itemIdentity)) {
                $rated = $index;

                break;
            }
        }

        if ($rated === null) {
            return null;
        }

        $count = count($steps);

        for ($offset = 1; $offset <= $count; $offset += 1) {
            $step = $steps[($rated + $offset) % $count];

            if ($step['rating'] === null) {
                return $step['number'];
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, ReviewItem>  $items
     * @return array<string, PracticeFeedbackEntry> keyed by kind and identity
     */
    private function entriesFor(Collection $items): array
    {
        return $this->candidates->entriesFor(
            $items
                ->map(fn (ReviewItem $item): array => [
                    'kind' => $item->kind,
                    'identity' => $item->item_identity,
                ])
                ->values()
                ->all(),
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

    /**
     * @param  Step  $step
     */
    private function matches(array $step, FeedbackType $kind, string $itemIdentity): bool
    {
        $card = $step['card'];

        return ($card['kind'] ?? null) === $kind->value
            && ($card['item_identity'] ?? null) === $itemIdentity;
    }
}
