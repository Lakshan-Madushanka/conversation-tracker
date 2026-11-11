<?php

declare(strict_types=1);

namespace App\Actions\Collections;

use App\Actions\Reviews\SyncReviewSchedule;
use App\Enums\FeedbackType;
use App\Models\CollectionItemMark;
use App\Models\PracticeFeedbackEntry;
use App\Models\Rating;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The typo rule: marks and review ratings follow an edited item.
 *
 * When an entry is edited so that its identity changes (a fixed typo, a
 * corrected mistake) — in a finished, non-archived session or on a notebook
 * board — what the learner has attached to the old words follows the words
 * they now see:
 *
 * - if the old item appears nowhere else, the marks and ratings move to the
 *   new item;
 * - if the old item still appears elsewhere, it keeps them and the edited text
 *   starts as its own item with none;
 * - if the new item already has marks or ratings, those are kept and nothing is
 *   overwritten; the old ones stay quietly stored under the old identity.
 *
 * Changes that only touch capitalisation, accents, spacing or formatting change
 * no identity, so nothing moves. This runs inside the same database transaction
 * as the session edit, is safe to repeat, and never touches grammar notes,
 * whose marks belong to the note itself.
 *
 * Ratings follow the same rule for every table row: a word, a phrase or a
 * mistake, whichever exercise kind its ratings were given in. Grammar notes
 * never carry ratings. After a move, the schedules of the old and the new
 * identity are rebuilt from the rating history, in the same transaction.
 */
final readonly class MoveMarkAfterEdit
{
    public function __construct(private SyncReviewSchedule $schedule) {}

    public function handle(
        FeedbackType $kind,
        string $fromIdentity,
        string $toIdentity,
        int $editedEntryId,
    ): void {
        if ($fromIdentity === $toIdentity) {
            return;
        }

        $fromKey = CollectionItemMark::keyFor($kind, $fromIdentity);
        $toKey = CollectionItemMark::keyFor($kind, $toIdentity);

        if ($fromKey === $toKey) {
            return;
        }

        DB::transaction(function () use ($kind, $fromKey, $toKey, $fromIdentity, $toIdentity, $editedEntryId): void {
            // The old item still appears in another finished session, so it
            // keeps the learner's marks and ratings for every place it shows.
            if ($this->appearsElsewhere($kind, $fromIdentity, $editedEntryId)) {
                return;
            }

            $this->moveMark($kind, $fromKey, $toKey, $toIdentity);
            $this->moveRatings($kind, $fromIdentity, $toIdentity);
        });
    }

    /** The star, status and notes follow the item when it has not been used. */
    private function moveMark(
        FeedbackType $kind,
        string $fromKey,
        string $toKey,
        string $toIdentity,
    ): void {
        /** @var CollectionItemMark|null $mark */
        $mark = CollectionItemMark::query()
            ->where('kind', $kind->value)
            ->where('item_key', $fromKey)
            ->lockForUpdate()
            ->first();

        // The old item carried no marks: the edited text starts fresh.
        if ($mark === null) {
            return;
        }

        // The edited text matches an item that already has marks. Those are
        // kept; nothing is overwritten, and the old marks stay stored under
        // the old identity, unused.
        $existing = CollectionItemMark::query()
            ->where('kind', $kind->value)
            ->where('item_key', $toKey)
            ->exists();

        if ($existing) {
            return;
        }

        $mark->item_key = $toKey;
        $mark->group_key = $toIdentity;
        $mark->save();
    }

    /**
     * The review history follows the item by the same rule, so an item does not
     * look never-reviewed just because its typo was fixed. Nothing is lost: a
     * new item that already has a rating keeps it.
     */
    private function moveRatings(
        FeedbackType $kind,
        string $fromIdentity,
        string $toIdentity,
    ): void {
        /** @var Collection<int, Rating> $ratings */
        $ratings = Rating::query()
            ->where('kind', $kind->value)
            ->where('item_identity', $fromIdentity)
            ->get();

        if ($ratings->isEmpty()) {
            return;
        }

        $targetAlreadyRated = Rating::query()
            ->where('kind', $kind->value)
            ->where('item_identity', $toIdentity)
            ->exists();

        if ($targetAlreadyRated) {
            return;
        }

        foreach ($ratings as $rating) {
            $rating->item_identity = $toIdentity;
            $rating->unique_key = Rating::keyFor($rating->review_id, $kind, $toIdentity);
            $rating->save();
        }

        // The moved history lands on a new identity, so both schedules are
        // rebuilt from the history: the old one may have nothing left, the
        // new one now carries the moved ratings.
        $this->schedule->handle($kind, $fromIdentity);
        $this->schedule->handle($kind, $toIdentity);
    }

    /**
     * Whether the old item has any other appearance in finished sessions that
     * are not archived, or in a notebook. The collection key is already stored
     * on every row by the same shared normalisation the collections group by,
     * so this is one lookup rather than reading and comparing every entry of
     * the type.
     */
    private function appearsElsewhere(
        FeedbackType $kind,
        string $fromIdentity,
        int $editedEntryId,
    ): bool {
        return PracticeFeedbackEntry::query()
            ->where('type', $kind->value)
            ->where('collection_key', $fromIdentity)
            ->whereKeyNot($editedEntryId)
            ->where(fn (Builder $scope): Builder => $scope
                ->whereNotNull('notebook_id')
                ->orWhereHas(
                    'practiceQuestion.practiceSession',
                    fn (Builder $query): Builder => $query->study(),
                ))
            ->exists();
    }
}
