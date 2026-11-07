<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use Carbon\CarbonImmutable;
use Database\Factories\CustomReviewAttemptItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item in one attempt, with the snapshot taken when the attempt started.
 *
 * `snapshot` holds the exact card the attempt showed; `snapshot_text` is the
 * same item's words for the history's list. Neither is ever updated, so later
 * edits to an item or its session leave past attempts as they were.
 *
 * The rating lives on this row alone, keyed so a repeated request counts once.
 * It is never a Review rating.
 *
 * @property int $id
 * @property int $custom_review_attempt_id
 * @property int $position
 * @property FeedbackType $kind
 * @property string $exercise
 * @property string $item_identity
 * @property string $snapshot_text
 * @property array<string, mixed> $snapshot
 * @property string $topic
 * @property CarbonImmutable|null $session_date
 * @property int|null $source_session_id
 * @property string|null $source_url
 * @property CarbonImmutable $snapshot_taken_at
 * @property ReviewRating|null $rating
 * @property CarbonImmutable|null $rated_at
 * @property string|null $rating_key
 * @property CarbonImmutable|null $skipped_at
 */
#[Fillable([
    'custom_review_attempt_id',
    'position',
    'kind',
    'exercise',
    'item_identity',
    'snapshot_text',
    'snapshot',
    'topic',
    'session_date',
    'source_session_id',
    'source_url',
    'snapshot_taken_at',
    'rating',
    'rated_at',
    'rating_key',
    'skipped_at',
])]
final class CustomReviewAttemptItem extends Model
{
    /** @use HasFactory<CustomReviewAttemptItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => FeedbackType::class,
            'snapshot' => 'array',
            'session_date' => 'immutable_datetime',
            'snapshot_taken_at' => 'immutable_datetime',
            'rating' => ReviewRating::class,
            'rated_at' => 'immutable_datetime',
            'skipped_at' => 'immutable_datetime',
        ];
    }

    /**
     * The one key a rating for this item in this attempt can ever have, so a
     * double tap or a retried request finds the same row instead of counting
     * again.
     */
    public static function ratingKeyFor(int $attemptId, FeedbackType $kind, string $itemIdentity): string
    {
        return 'attempt-rating-'.sha1($attemptId."\u{0}".$kind->value."\u{0}".$itemIdentity);
    }

    /**
     * @return BelongsTo<CustomReviewAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CustomReviewAttempt::class, 'custom_review_attempt_id');
    }

    public function isRated(): bool
    {
        return $this->rating !== null;
    }

    public function isSkipped(): bool
    {
        return $this->skipped_at !== null;
    }

    /** Whether this item has nothing left for the attempt to record. */
    public function isSettled(): bool
    {
        return $this->isRated() || $this->isSkipped();
    }
}
