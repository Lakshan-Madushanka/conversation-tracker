<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One review sitting: the fixed list of items the learner works through.
 *
 * The review keeps no copy of the items' text. It names them by identity, so
 * the current words are read again whenever a card is shown and a review left
 * open for a long time still shows what the learner actually saved.
 *
 * @property int $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([])]
final class Review extends Model
{
    /**
     * The items in the order the learner meets them.
     *
     * @return HasMany<ReviewItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReviewItem::class)->orderBy('position');
    }

    /**
     * The ratings given so far, one per item at most.
     *
     * @return HasMany<Rating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }
}
