<?php

declare(strict_types=1);

namespace App\Actions\Basket;

use App\Enums\FeedbackType;
use App\Models\BasketItem;
use Illuminate\Support\Facades\DB;

/**
 * Takes items out of the basket, by identity. Removing something that is not
 * there is not an error; the answer simply says nothing was removed, so a
 * repeated request stays safe.
 */
final class RemoveBasketItems
{
    /**
     * @param  list<string>  $identities
     * @return array{removed: list<string>, total: int}
     */
    public function handle(FeedbackType $kind, array $identities): array
    {
        $identities = array_values(array_unique(array_filter(
            $identities,
            fn (string $identity): bool => $identity !== '',
        )));

        return DB::transaction(function () use ($kind, $identities): array {
            if ($identities === []) {
                return ['removed' => [], 'total' => BasketItem::query()->count()];
            }

            /** @var list<string> $removed */
            $removed = BasketItem::query()
                ->where('kind', $kind->value)
                ->whereIn('item_identity', $identities)
                ->pluck('item_identity')
                ->all();

            BasketItem::query()
                ->where('kind', $kind->value)
                ->whereIn('item_identity', $identities)
                ->delete();

            return [
                'removed' => $removed,
                'total' => BasketItem::query()->count(),
            ];
        });
    }
}
