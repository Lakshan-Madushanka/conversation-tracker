<?php

declare(strict_types=1);

namespace App\Actions\Collections;

use App\Enums\CollectionItemStatus;
use App\Enums\FeedbackType;
use App\Models\CollectionItemMark;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Saves the learner's star and status for one collection item, from the state
 * they asked for.
 *
 * The request always names the desired state, never a toggle, so a repeated
 * or retried request, or the same choice from two tabs, cannot flip the
 * outcome. Requests may carry one field or both; the notes stay untouched.
 * Nothing here touches any session, entry or Progress count.
 */
final class SaveItemMark
{
    public function handle(
        FeedbackType $kind,
        string $itemKey,
        ?bool $starred,
        ?CollectionItemStatus $status,
    ): CollectionItemMark {
        $storageKey = CollectionItemMark::keyFor($kind, $itemKey);

        return DB::transaction(function () use ($kind, $storageKey, $starred, $status): CollectionItemMark {
            /** @var CollectionItemMark|null $mark */
            $mark = CollectionItemMark::query()
                ->where('kind', $kind->value)
                ->where('item_key', $storageKey)
                ->lockForUpdate()
                ->first();

            if ($mark === null) {
                return $this->create($kind, $storageKey, $starred, $status);
            }

            if ($starred !== null && $mark->starred !== $starred) {
                $mark->starred = $starred;
                $mark->markChanged();
            }

            // The request always carries the status: a value means the
            // learner chose it, and none means they want the status gone.
            if ($mark->status !== $status) {
                $mark->status = $status;
                $mark->markChanged();
            }

            $mark->save();

            return $mark;
        });
    }

    /**
     * First mark for this item. Requests that arrive together share the same
     * key, so the winner keeps its row and the loser adopts it — at most one
     * record per learner and item.
     */
    private function create(
        FeedbackType $kind,
        string $storageKey,
        ?bool $starred,
        ?CollectionItemStatus $status,
    ): CollectionItemMark {
        try {
            $mark = CollectionItemMark::query()->make([
                'kind' => $kind,
                'item_key' => $storageKey,
                'starred' => $starred ?? false,
                'status' => $status,
                // A first mark starts with no note at all.
                'notes_html' => '',
                'notes_text' => '',
                'notes_version' => 0,
            ]);
            $mark->markChanged();
            $mark->save();

            return $mark;
        } catch (UniqueConstraintViolationException) {
            $mark = CollectionItemMark::query()
                ->where('kind', $kind->value)
                ->where('item_key', $storageKey)
                ->firstOrFail();

            // The row exists; someone else created it a moment ago. Apply the
            // same desired state once more instead of writing a second row.
            $mark->starred = $starred ?? $mark->starred;
            $mark->status = $status;

            $mark->markChanged();
            $mark->save();

            return $mark;
        }
    }
}
