<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One review sitting: the fixed list of items the learner works through.
 *
 * The review keeps no copy of the items' text. It names them by identity, so
 * the current words are read again whenever a card is shown and a review left
 * open for a long time still shows what the learner actually saved.
 *
 * `is_daily_review` marks a review started from the day's suggestion;
 * `completed_on` is the learner's local day the finish screen was reached,
 * so "You've reviewed today" can be worked out. A review left part-way
 * keeps `completed_on` null.
 *
 * @property int $id
 * @property bool $is_daily_review
 * @property CarbonImmutable|null $completed_on
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['is_daily_review', 'completed_on'])]
final class Review extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_daily_review' => 'boolean',
            'completed_on' => 'immutable_date',
        ];
    }

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
