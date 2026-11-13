<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Models\CustomReview;
use App\Models\CustomReviewItem;
use App\Models\Notebook;
use App\Models\PracticeFeedbackEntry;
use App\Support\CustomReviewDeletedIdentities;
use App\Support\NotebookDeletionEffect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What deleting a notebook would remove, worked out without changing anything.
 *
 * Saved rows are counted per box, while the study items those rows make are
 * counted by their scoped identity, so duplicate rows are visible as one item.
 * The saved custom reviews that hold any of those identities are named with
 * how many of their items would go and whether that empties them. Nothing here
 * writes: Collections marks, daily schedules and attempt snapshots are left to
 * the deletion itself.
 */
final readonly class NotebookDeletionPreview
{
    public function __construct(
        private CustomReviewItems $reviewItems,
    ) {}

    public function for(Notebook $notebook): NotebookDeletionEffect
    {
        $notebook->load(['topic', 'entries']);

        /** @var Collection<int, PracticeFeedbackEntry> $entries */
        $entries = $notebook->entries->reject(
            fn (PracticeFeedbackEntry $entry): bool => $entry->isBlank(),
        );

        $counts = ['words' => 0, 'phrases' => 0, 'grammar_notes' => 0];

        foreach ($entries as $entry) {
            match ($entry->type) {
                FeedbackType::Vocabulary => $counts['words']++,
                FeedbackType::Phrase => $counts['phrases']++,
                FeedbackType::GrammarNote => $counts['grammar_notes']++,
                default => null,
            };
        }

        $items = CustomReviewDeletedIdentities::grouped($entries, []);

        return new NotebookDeletionEffect(
            notebook: $notebook,
            counts: $counts,
            items: $items,
            reviews: $this->affectedReviews($items),
        );
    }

    /**
     * The saved custom reviews holding at least one of these identities, with
     * how many of their items would leave and whether the review is left empty.
     *
     * @param  array<string, list<string>>  $items
     * @return list<array{id: int, name: string, removed: int, remaining: int, becomes_empty: bool}>
     */
    private function affectedReviews(array $items): array
    {
        $query = $this->heldBy($items);

        if (! $query instanceof Builder) {
            return [];
        }

        /** @var Collection<int, CustomReviewItem> $held */
        $held = $query->get(['custom_review_id']);

        /** @var array<int, int> $removedByReview */
        $removedByReview = $held
            ->countBy(fn (CustomReviewItem $item): int => (int) $item->custom_review_id)
            ->all();

        $reviewIds = array_keys($removedByReview);
        $totals = $this->reviewItems->counts($reviewIds);

        /** @var Collection<int, CustomReview> $reviews */
        $reviews = CustomReview::query()
            ->whereIn('id', $reviewIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name']);

        $affected = [];

        foreach ($reviews as $review) {
            $removed = $removedByReview[$review->id] ?? 0;
            $remaining = max(0, ($totals[$review->id] ?? 0) - $removed);

            $affected[] = [
                'id' => (int) $review->id,
                'name' => $review->name,
                'removed' => $removed,
                'remaining' => $remaining,
                'becomes_empty' => $remaining === 0,
            ];
        }

        return $affected;
    }

    /**
     * The review references that would go, one grouped condition per kind. A
     * null answer means nothing is held anywhere.
     *
     * @param  array<string, list<string>>  $items
     * @return Builder<CustomReviewItem>|null
     */
    private function heldBy(array $items): ?Builder
    {
        $groups = array_filter(
            $items,
            fn (array $identities): bool => $identities !== [],
        );

        if ($groups === []) {
            return null;
        }

        return CustomReviewItem::query()->where(
            function (Builder $group) use ($groups): void {
                foreach ($groups as $kind => $identities) {
                    $group->orWhere(
                        fn (Builder $pair): Builder => $pair
                            ->where('kind', $kind)
                            ->whereIn('item_identity', $identities),
                    );
                }
            },
        );
    }
}
