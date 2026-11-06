<?php

declare(strict_types=1);

namespace App\Actions\Basket;

use App\Models\BasketItem;

/**
 * Empties the basket. Nothing else is touched, so no item's marks, ratings,
 * schedule or session changes.
 */
final class ClearBasket
{
    /** @return int how many items were removed */
    public function handle(): int
    {
        $count = BasketItem::query()->count();

        BasketItem::query()->delete();

        return $count;
    }
}
