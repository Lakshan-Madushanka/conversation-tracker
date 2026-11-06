<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Models\BasketItem;

/**
 * Whether the items on screen are already in the basket, read in bulk.
 *
 * One lookup covers a whole page of collection items or every question in a
 * session, so the number of queries never grows with the list.
 */
final readonly class BasketItems
{
    /**
     * The subset of these identities that is in the basket.
     *
     * @param  list<string>  $identities
     * @return array<string, true>
     */
    public function among(FeedbackType $kind, array $identities): array
    {
        $identities = array_values(array_unique(array_filter(
            $identities,
            fn (string $identity): bool => $identity !== '',
        )));

        if ($identities === []) {
            return [];
        }

        /** @var list<string> $found */
        $found = BasketItem::query()
            ->where('kind', $kind->value)
            ->whereIn('item_identity', $identities)
            ->pluck('item_identity')
            ->all();

        return array_fill_keys($found, true);
    }

    /** Whether one item, such as the one open in a panel, is in the basket. */
    public function has(FeedbackType $kind, string $identity): bool
    {
        return BasketItem::query()
            ->where('kind', $kind->value)
            ->where('item_identity', $identity)
            ->exists();
    }

    /**
     * The questions of a session that are already in the basket.
     *
     * @param  list<int>  $questionIds
     * @return list<int>
     */
    public function questions(array $questionIds): array
    {
        $found = $this->among(
            FeedbackType::Question,
            array_map(fn (int $id): string => (string) $id, $questionIds),
        );

        return array_map(intval(...), array_keys($found));
    }
}
