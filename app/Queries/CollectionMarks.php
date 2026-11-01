<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Models\CollectionItemMark;
use Illuminate\Support\Collection;

/**
 * Reads the marks the learner keeps for collection items, in bulk.
 *
 * A page of items gets its marks with one request that looks up every key at
 * once, so the number of database queries never grows with the number of
 * items on the page. Items the learner has not marked have no row here; the
 * page shows their defaults.
 */
final readonly class CollectionMarks
{
    /**
     * The marks for a page of items, keyed by the item key the page uses.
     *
     * @param  list<string>  $itemKeys
     * @return array<string, CollectionItemMark>
     */
    public function forItems(FeedbackType $kind, array $itemKeys): array
    {
        $keys = array_values(array_unique($itemKeys));

        if ($keys === []) {
            return [];
        }

        /** @var Collection<string, string> $groupKeyByStorageKey */
        $groupKeyByStorageKey = collect($keys)->mapWithKeys(
            fn (string $key): array => [CollectionItemMark::keyFor($kind, $key) => $key],
        );

        $marks = CollectionItemMark::query()
            ->where('kind', $kind->value)
            ->whereIn('item_key', $groupKeyByStorageKey->keys()->all())
            ->get()
            ->keyBy(fn (CollectionItemMark $mark): string => $mark->item_key);

        /** @var array<string, CollectionItemMark> $byGroupKey */
        $byGroupKey = [];

        foreach ($groupKeyByStorageKey as $storageKey => $groupKey) {
            $mark = $marks->get($storageKey);

            if ($mark !== null) {
                $byGroupKey[$groupKey] = $mark;
            }
        }

        return $byGroupKey;
    }

    /** One item's marks, or null when the learner has not marked it. */
    public function forItem(FeedbackType $kind, string $itemKey): ?CollectionItemMark
    {
        /** @var CollectionItemMark|null */
        return CollectionItemMark::query()
            ->forItem($kind, $itemKey)
            ->first();
    }
}
