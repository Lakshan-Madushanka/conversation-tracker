<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use App\Models\Rating;
use App\Models\Review;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Saves the learner's rating for one item, straight away and exactly once.
 *
 * The key is derived from the review, the item's kind and its identity, so a
 * double tap, a retried request or the same request from two tabs finds the
 * same row. The first rating wins; a repeat changes nothing and never counts
 * twice.
 */
final readonly class RecordRating
{
    public function handle(
        Review $review,
        FeedbackType $kind,
        string $itemIdentity,
        ReviewRating $rating,
    ): Rating {
        $key = Rating::keyFor($review->id, $kind, $itemIdentity);

        $attributes = [
            'review_id' => $review->id,
            'kind' => $kind,
            'item_identity' => $itemIdentity,
            'rating' => $rating,
            'unique_key' => $key,
        ];

        try {
            /** @var Rating $saved */
            $saved = Rating::query()->firstOrCreate(['unique_key' => $key], $attributes);

            return $saved;
        } catch (UniqueConstraintViolationException) {
            // The same rating arrived at the same moment. The row that won is
            // the one rating that counts.
            /** @var Rating $existing */
            $existing = Rating::query()->where('unique_key', $key)->firstOrFail();

            return $existing;
        }
    }
}
