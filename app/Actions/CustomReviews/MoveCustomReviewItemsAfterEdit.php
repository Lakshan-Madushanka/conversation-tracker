<?php

declare(strict_types=1);

namespace App\Actions\CustomReviews;

use App\Enums\FeedbackType;
use App\Models\CustomReviewItem;
use App\Models\PracticeFeedbackEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The typo rule for custom reviews: a reference follows the words it was added
 * from when an edit changes the item's identity.
 *
 * If the old identity appears nowhere else, every review holding it moves that
 * reference to the new identity. Where a review already holds the new identity
 * too, its old reference is dropped, so one item never has two rows in the
 * same review and no review loses an item it did not have before. Grammar
 * notes and questions keep their identity, so nothing can move for them.
 *
 * Nothing else is touched — no marks, ratings, schedule or session.
 */
final class MoveCustomReviewItemsAfterEdit
{
    public function handle(
        FeedbackType $kind,
        string $fromIdentity,
        string $toIdentity,
        int $editedEntryId,
    ): void {
        if ($fromIdentity === $toIdentity || ! $kind->isMarkable()) {
            return;
        }

        DB::transaction(function () use ($kind, $fromIdentity, $toIdentity, $editedEntryId): void {
            if ($this->appearsElsewhere($kind, $fromIdentity, $editedEntryId)) {
                return;
            }

            /** @var list<CustomReviewItem> $rows */
            $rows = CustomReviewItem::query()
                ->where('kind', $kind->value)
                ->where('item_identity', $fromIdentity)
                ->lockForUpdate()
                ->get()
                ->all();

            if ($rows === []) {
                return;
            }

            // The reviews that already hold the new identity. Dropping their
            // old row leaves each of them with at least one item, so no status
            // needs following here.
            $alreadyMoved = CustomReviewItem::query()
                ->where('kind', $kind->value)
                ->where('item_identity', $toIdentity)
                ->pluck('custom_review_id')
                ->flip();

            foreach ($rows as $row) {
                if ($alreadyMoved->has((int) $row->custom_review_id)) {
                    $row->delete();

                    continue;
                }

                $row->item_identity = $toIdentity;
                $row->save();
            }
        });
    }

    /** Whether the old item still appears in another finished, live session. */
    private function appearsElsewhere(
        FeedbackType $kind,
        string $identity,
        int $editedEntryId,
    ): bool {
        return PracticeFeedbackEntry::query()
            ->where('type', $kind->value)
            ->where('collection_key', $identity)
            ->whereKeyNot($editedEntryId)
            ->whereHas(
                'practiceQuestion.practiceSession',
                fn (Builder $query): Builder => $query->study(),
            )
            ->exists();
    }
}
