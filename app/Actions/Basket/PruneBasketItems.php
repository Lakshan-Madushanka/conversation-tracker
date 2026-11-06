<?php

declare(strict_types=1);

namespace App\Actions\Basket;

use App\Enums\FeedbackType;
use App\Models\BasketItem;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Support\BasketIdentity;
use App\Support\MistakeIdentity;
use Illuminate\Support\Collection;

/**
 * Removes basket references whose item no longer exists at all.
 *
 * An item that is only archived still exists, so it stays and is shown as not
 * available right now. An item whose last session was deleted has nothing left
 * to point at, so its reference is removed for good instead of leaving a
 * ghost row or an empty line.
 *
 * This is the one repair a basket read performs, called in the controller when
 * the basket page opens, so every view of the basket keeps the same rule.
 */
final class PruneBasketItems
{
    /** @return int how many references were removed */
    public function handle(): int
    {
        /** @var Collection<string, Collection<int, BasketItem>> $byKind */
        $byKind = BasketItem::query()
            ->get(['id', 'kind', 'item_identity'])
            ->groupBy(fn (BasketItem $item): string => $item->kind->value);

        $removed = 0;

        foreach ($byKind as $kindValue => $rows) {
            $kind = FeedbackType::tryFrom($kindValue);

            /** @var list<string> $identities */
            $identities = $rows
                ->map(fn (BasketItem $item): string => $item->item_identity)
                ->unique()
                ->values()
                ->all();

            if ($kind === null || ! $kind->isBasketType()) {
                $removed += $this->delete($identities, $kindValue);

                continue;
            }

            $live = $this->liveIdentities($kind, $identities);
            $missing = array_values(array_diff($identities, $live));

            $removed += $this->delete($missing, $kindValue);
        }

        return $removed;
    }

    /**
     * The identities of one kind that still have a source row, including rows
     * in archived sessions. Availability is decided later; here only existence
     * matters.
     *
     * @param  list<string>  $identities
     * @return list<string>
     */
    private function liveIdentities(FeedbackType $kind, array $identities): array
    {
        if ($identities === []) {
            return [];
        }

        if ($kind === FeedbackType::GrammarNote) {
            $ids = array_values(array_filter(array_map(
                BasketIdentity::noteId(...),
                $identities,
            )));

            if ($ids === []) {
                return [];
            }

            /** @var list<string> $keys */
            $keys = PracticeFeedbackEntry::query()
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(fn (int $id): string => BasketIdentity::noteKey($id))
                ->all();

            return $keys;
        }

        if ($kind === FeedbackType::Mistake) {
            $wanted = array_fill_keys($identities, true);
            $live = [];

            // The pair key is derived from both columns, so the rows of this
            // kind are read and matched by the same rule the collections use.
            PracticeFeedbackEntry::query()
                ->where('type', FeedbackType::Mistake->value)
                ->select(['id', 'content_text', 'correction_text'])
                ->chunkById(500, function (Collection $rows) use ($wanted, &$live): void {
                    foreach ($rows as $row) {
                        $said = trim((string) $row->content_text);
                        $correction = trim((string) $row->correction_text);

                        if (MistakeIdentity::isBlank($said, $correction)) {
                            continue;
                        }

                        $key = MistakeIdentity::pairKey($said, $correction);

                        if (isset($wanted[$key])) {
                            $live[$key] = true;
                        }
                    }
                });

            return array_keys($live);
        }

        if ($kind === FeedbackType::Question) {
            /** @var list<string> $ids */
            $ids = PracticeQuestion::query()
                ->whereIn('id', array_map(intval(...), $identities))
                ->pluck('id')
                ->map(fn (int $id): string => (string) $id)
                ->all();

            return $ids;
        }

        /** @var list<string> $keys */
        $keys = PracticeFeedbackEntry::query()
            ->where('type', $kind->value)
            ->whereIn('collection_key', $identities)
            ->pluck('collection_key')
            ->unique()
            ->values()
            ->all();

        return $keys;
    }

    /**
     * @param  list<string>  $identities
     */
    private function delete(array $identities, string $kind): int
    {
        if ($identities === []) {
            return 0;
        }

        return BasketItem::query()
            ->where('kind', $kind)
            ->whereIn('item_identity', $identities)
            ->delete();
    }
}
