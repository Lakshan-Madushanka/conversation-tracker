<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

/**
 * A rating given by a learner for an item in a review.
 *
 * One row per (review, item, kind) combination. The unique_key ensures that
 * a double tap, retry, or repeated request is counted only once.
 *
 * Ratings are tied to the item's normalized identity (same as Collections uses),
 * not to a specific collection_item_mark row. This means they follow the item
 * when its session is edited, a typo is fixed, or it appears in different sessions.
 *
 * @property int $id
 * @property int $review_id
 * @property string $item_identity  normalized key matching Collections' item identity
 * @property string $kind  'word' or 'phrase'
 * @property string $rating  'hard', 'okay', or 'easy'
 * @property string $unique_key  used to make saving safe to repeat
 * @property string $given_at
 * @property bool $is_pending
 * @property Carbon|null $resolved_at
 *
 * @property Review $review
 */
#[Fillable([
    'review_id',
    'item_identity',
    'kind',
    'rating',
    'unique_key',
    'is_pending',
])]
class Rating extends Model
{
    /** @use HasFactory<Database\Factories\RatingFactory> */
    use HasFactory;

    /**
     * Boot the model and set up saving hooks.
     */
    protected static function booted(): void
    {
        static::saving(function (self $rating): void {
            // Ensure unique_key is set - generate one if not provided
            if (trim($rating->unique_key) === '') {
                $rating->unique_key = 'rating-'.substr(sha1(
                    $rating->kind . "\u{0}" . $rating->item_identity . "\u{0}" . now(),
                ), 0, 20);
            }
        });

        static::saved(function (self $rating): void {
            // Mark as resolved if it was pending
            if ($rating->is_pending) {
                $rating->forceFill(['is_pending' => false, 'resolved_at' => now()])->saveQuietly();
            }
        });
    }

    /**
     * Scope a query to only ratings for a given review.
     */
    public function scopeForReview(Builder $query, int $reviewId): Builder
    {
        return $query->where('review_id', $reviewId);
    }

    /**
     * Scope to only ratings that have been resolved (not pending).
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('is_pending', false);
    }

    /**
     * Scope to only ratings for a given item identity and kind.
     * This is the primary way to look up ratings by item identity.
     */
    public function scopeForItem(Builder $query, string $itemIdentity, string $kind): Builder
    {
        return $query
            ->where('item_identity', $itemIdentity)
            ->where('kind', $kind);
    }

    /**
     * Get the review this rating belongs to.
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class, 'review_id');
    }
}