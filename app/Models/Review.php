<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\SearchText;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A review session containing a fixed set of items for the learner to practice.
 *
 * Each review has a fixed list of items (up to 8 by default) that was
 * determined when the review was started. Items are read fresh when shown,
 * so edits appear immediately. The review's address (URL) persists so
 * a refresh returns to the same review at the next unrated item.
 *
 * @property int $id
 * @property int $learner_id
 * @property int $session_id
 * @property string $item_identity  normalized key matching Collections' item identity
 * @property string $item_type  'word' or 'phrase'
 * @property bool $is_reviewed_first_time
 * @property json  metadata  flexible metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property CollectionItemMark|null $mark  the most recent mark for this item type
 */
#[Fillable([
    'learner_id',
    'session_id',
    'item_identity',
    'item_type',
    'is_reviewed_first_time',
    'metadata',
])]
class Review extends Model
{
    /** @use HasFactory<Database\Factories\ReviewFactory> */
    use HasFactory;

    /**
     * The reviewable item identities for this review, in the order they were selected.
     * This is loaded fresh when needed rather than cached, because items are read
     * fresh when shown (so edits appear).
     *
     * @var string[]|null
     */
    protected static function booted(): void
    {
        static::saving(function (self $review): void {
            // Ensure metadata is always an array
            if (! is_array($review->metadata)) {
                $review->metadata = (array) $review->metadata;
            }
        });
    }

    /**
     * Scope a query to only reviews for this learner.
     */
    #[Scope]
    protected function learner(Builder $query, int $learnerId): Builder
    {
        return $query->where('learner_id', $learnerId);
    }

    /**
     * Get the learner for the review.
     */
    public function learner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'learner_id');
    }

    /**
     * Get the practice session for the review.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PracticeSession::class, 'session_id');
    }

    /**
     * Check if this item has already been rated in this review.
     */
    public function hasRating(): bool
    {
        return ! is_null($this->rating());
    }

    /**
     * Get the rating for this item, if any.
     */
    public function rating(): ?string
    {
        $rating = $this->hasOne(Rating::class, 'item_id', 'item_identity')
            ->where('review_id', $this->id)
            ->where('kind', $this->item_type);

        return $rating ? $rating->rating : null;
    }

    /**
     * Get the unique key for this item's rating, if any.
     */
    public function uniqueKey(): ?string
    {
        $key = $this->hasOne(Rating::class, 'item_id', 'item_identity')
            ->where('review_id', $this->id)
            ->where('kind', $this->item_type)
            ->value('unique_key');

        return $key;
    }
}