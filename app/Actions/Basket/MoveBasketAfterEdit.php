<?php

declare(strict_types=1);

namespace App\Actions\Basket;

use App\Enums\FeedbackType;
use App\Models\BasketItem;
use App\Models\PracticeFeedbackEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The typo rule for the basket: a reference follows the words it was added
 * from when an edit changes the item's identity.
 *
 * If the old identity appears nowhere else, its basket reference moves to the
 * new identity. If the new identity is already in the basket the old
 * reference is dropped, so one item never has two rows. Grammar notes and
 * questions keep their identity, so nothing can move for them.
 *
 * Nothing else is touched — no marks, ratings, schedule or session.
 */
final class MoveBasketAfterEdit
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

            /** @var BasketItem|null $source */
            $source = BasketItem::query()
                ->where('kind', $kind->value)
                ->where('item_identity', $fromIdentity)
                ->lockForUpdate()
                ->first();

            if ($source === null) {
                return;
            }

            $targetExists = BasketItem::query()
                ->where('kind', $kind->value)
                ->where('item_identity', $toIdentity)
                ->exists();

            if ($targetExists) {
                $source->delete();

                return;
            }

            $source->item_identity = $toIdentity;
            $source->save();
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
