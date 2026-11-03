<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * How the learner judged one item in one review.
 *
 * The row is keyed by the item's identity, never by a session, so ratings can
 * follow a typo fix and wait for an item that disappears and comes back. The
 * `unique_key` is derived from the review, the kind and the identity, so a
 * double tap or a retried request finds the same row instead of writing a
 * second one.
 *
 * @property int $id
 * @property int $review_id
 * @property FeedbackType $kind
 * @property string $item_identity
 * @property ReviewRating $rating
 * @property string $unique_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['review_id', 'kind', 'item_identity', 'rating', 'unique_key'])]
final class Rating extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => FeedbackType::class,
            'rating' => ReviewRating::class,
        ];
    }

    /**
     * The one key a rating for this item in this review can ever have. A
     * repeated request computes the same key, so it is recognised instead of
     * counted again.
     */
    public static function keyFor(int $reviewId, FeedbackType $kind, string $itemIdentity): string
    {
        return 'rating-'.sha1($reviewId."\u{0}".$kind->value."\u{0}".$itemIdentity);
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
