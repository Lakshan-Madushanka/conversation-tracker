<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Enums\ItemAvailability;
use App\Models\CustomReviewAttempt;
use App\Models\CustomReviewAttemptItem;
use App\Support\CustomReviewPractice;
use App\Support\ReviewSectionBoard;
use Illuminate\Support\Collection;

/**
 * The steps an attempt is walked now, read from the snapshots it started with.
 *
 * The items never look at the live word, question or session: the stored card
 * is exactly what the attempt showed, so an edit made after the attempt cannot
 * rewrite it. Grouping, progress and navigation come from the same board Review
 * uses, so the two screens behave alike.
 *
 * One thing can still reach a running attempt from outside: the session an
 * item came from being archived. Its card cannot be shown again, so the item is
 * left out of the walk until the session comes back, and what the learner rated
 * in it stays. A finished attempt keeps what it recorded, and nothing that
 * happened afterwards reaches into it.
 *
 * @phpstan-import-type Section from ReviewSectionBoard
 */
final readonly class AttemptSections
{
    public function __construct(
        private ReviewSectionBoard $board,
        private CustomReviewAvailability $availability,
    ) {}

    /**
     * The walk itself: the sections in order, the items met inside them, and
     * the kinds that have nothing left to show.
     *
     * @return array{
     *     sections: list<Section>,
     *     items: Collection<int, CustomReviewAttemptItem>,
     *     notices: list<string>
     * }
     */
    public function walk(CustomReviewAttempt $attempt): array
    {
        $items = $attempt->items;
        $notices = [];

        if ($attempt->isInProgress()) {
            $left = $this->showable($items);
            $notices = $this->notices($items, $left);
            $items = $left;
        }

        $sections = $this->board->build(
            $this->cards($items),
            fn (string $exercise, int $position): string => route('customReviews.attempts.show', [
                'customReview' => $attempt->custom_review_id,
                'attempt' => $attempt->id,
                'step' => $exercise,
                'position' => $position,
            ]),
        );

        return [
            'sections' => $sections,
            'items' => $items->values(),
            'notices' => $notices,
        ];
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

    /**
     * The items still met, in the order the attempt holds them.
     *
     * @param  Collection<int, CustomReviewAttemptItem>  $items
     * @return Collection<int, CustomReviewAttemptItem>
     */
    private function showable(Collection $items): Collection
    {
        if ($items->isEmpty()) {
            return $items;
        }

        /** @var array<string, ItemAvailability> $states */
        $states = [];

        foreach ($this->byKind($items) as $kindValue => $rows) {
            $kind = FeedbackType::from($kindValue);

            /** @var list<string> $identities */
            $identities = array_values(array_unique($rows->map(
                fn (CustomReviewAttemptItem $item): string => $item->item_identity,
            )->all()));

            foreach ($this->availability->states($kind, $identities) as $identity => $state) {
                $states[$kindValue."\u{0}".$identity] = $state;
            }
        }

        return $items->filter(
            fn (CustomReviewAttemptItem $item): bool => ($states[$item->kind->value."\u{0}".$item->item_identity] ?? ItemAvailability::Available)->isAvailable(),
        );
    }

    /**
     * The kinds the learner came here to practise and cannot meet at all, each
     * named once in the attempt's own calm words.
     *
     * @param  Collection<int, CustomReviewAttemptItem>  $items
     * @param  Collection<int, CustomReviewAttemptItem>  $showable
     * @return list<string>
     */
    private function notices(Collection $items, Collection $showable): array
    {
        $met = $showable
            ->map(fn (CustomReviewAttemptItem $item): string => $item->kind->value)
            ->unique();

        $notices = [];

        foreach ($this->byKind($items)->keys() as $kindValue) {
            if (! $met->contains($kindValue)) {
                $notices[] = CustomReviewPractice::leftOut(FeedbackType::from($kindValue));
            }
        }

        return $notices;
    }

    /**
     * The attempt's items, grouped under the kind they name.
     *
     * @param  Collection<int, CustomReviewAttemptItem>  $items
     * @return Collection<string, Collection<int, CustomReviewAttemptItem>>
     */
    private function byKind(Collection $items): Collection
    {
        return $items->groupBy(fn (CustomReviewAttemptItem $item): string => $item->kind->value);
    }

    /**
     * The cards the board walks.
     *
     * @param  Collection<int, CustomReviewAttemptItem>  $items
     * @return list<array{position: int, kind: FeedbackType, identity: string, exercise: string, card: array<string, mixed>, rating: string|null, skipped: bool}>
     */
    private function cards(Collection $items): array
    {
        $cards = [];

        foreach ($items as $item) {
            $cards[] = [
                'position' => $item->position,
                'kind' => $item->kind,
                'identity' => $item->item_identity,
                'exercise' => $item->exercise,
                'card' => $item->snapshot,
                'rating' => $item->rating?->value,
                'skipped' => $item->isSkipped(),
            ];
        }

        return $cards;
    }
}
