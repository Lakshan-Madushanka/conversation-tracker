<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\BasketItem;

/**
 * How many items the basket holds. The basket bar and its link need only this
 * number, so they never read the items themselves.
 */
final readonly class BasketCount
{
    public function value(): int
    {
        return BasketItem::query()->count();
    }
}
