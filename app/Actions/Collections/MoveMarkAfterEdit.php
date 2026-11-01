<?php

declare(strict_types=1);

namespace App\Actions\Collections;

use App\Enums\FeedbackType;
use App\Models\CollectionItemMark;
use App\Models\PracticeFeedbackEntry;
use App\Support\MistakeIdentity;
use App\Support\SearchText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The typo rule: marks follow an edited item.
 *
 * When an entry in a finished, non-archived session is edited so that its
 * identity changes (a fixed typo, a corrected mistake), the item's star,
 * status and notes follow the words the learner now sees:
 *
 * - if the old item appears nowhere else, the marks move to the new item;
 * - if the old item still appears elsewhere, it keeps its marks and the
 *   edited text starts as its own item with none;
 * - if the new item already has marks, those are kept and nothing is
 *   overwritten; the old marks stay quietly stored under the old identity.
 *
 * Changes that only touch capitalisation, accents, spacing or formatting
 * change no identity, so nothing moves. This runs inside the same database
 * transaction as the session edit, is safe to repeat, and never touches
 * grammar notes, whose marks belong to the note itself.
 */
final readonly class MoveMarkAfterEdit
{
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

        DB::transaction(function () use ($kind, $fromKey, $toKey, $fromIdentity, $editedEntryId): void {
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

            // The old item still appears in another finished session, so it
            // keeps the learner's marks for every place it shows up.
            if ($this->appearsElsewhere($kind, $fromIdentity, $editedEntryId)) {
                return;
            }

            // The edited text matches an item that already has marks. Those
            // are kept; nothing is overwritten, and the old marks stay stored
            // under the old identity, unused.
            $existing = CollectionItemMark::query()
                ->where('kind', $kind->value)
                ->where('item_key', $toKey)
                ->exists();

            if ($existing) {
                return;
            }

            $mark->item_key = $toKey;
            $mark->save();
        });
    }

    /**
     * Whether the old item has any other appearance in finished sessions that
     * are not archived. This is a lone check on an edited entry, not a list
     * build, so it reads the few columns it needs and compares identities the
     * same way the collections do — the shared normalisation, so a difference
     * in capitals, accents or spacing never reads as a different item.
     */
    private function appearsElsewhere(
        FeedbackType $kind,
        string $fromIdentity,
        int $editedEntryId,
    ): bool {
        return PracticeFeedbackEntry::query()
            ->select(['id', 'content_text', 'correction_text'])
            ->where('type', $kind->value)
            ->whereKeyNot($editedEntryId)
            ->whereHas(
                'practiceQuestion.practiceSession',
                fn (Builder $query): Builder => $query->study(),
            )
            ->get()
            ->contains(
                fn (PracticeFeedbackEntry $entry): bool => $this->identityOf($kind, $entry) === $fromIdentity,
            );
    }

    /**
     * The identity an entry holds, by the same rule the collections group by.
     * Grammar notes never come through here: their marks belong to the note
     * itself, whatever its text becomes.
     */
    private function identityOf(FeedbackType $kind, PracticeFeedbackEntry $entry): ?string
    {
        $content = (string) $entry->content_text;
        $correction = (string) $entry->correction_text;

        if ($kind === FeedbackType::Mistake) {
            return MistakeIdentity::isBlank($content, $correction)
                ? null
                : MistakeIdentity::pairKey($content, $correction);
        }

        $identity = SearchText::normalize($content);

        return $identity === '' ? null : $identity;
    }
}
