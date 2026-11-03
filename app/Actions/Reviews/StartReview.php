<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Models\Review;
use App\Models\ReviewItem;
use App\Queries\ReviewCandidates;
use Illuminate\Support\Facades\DB;

/**
 * Starts a review from the items most worth revisiting right now.
 *
 * The list is fixed when the review starts, so a refresh, a bookmark or a
 * retry returns to the same sitting and to the next item not yet rated. The
 * items are named by identity only; their words are read again when shown.
 */
final readonly class StartReview
{
    public function __construct(private ReviewCandidates $candidates) {}

    /**
     * Nothing reviewable means no review: the caller shows the friendly empty
     * state rather than an empty sitting.
     */
    public function handle(): ?Review
    {
        $items = $this->candidates->next();

        if ($items === []) {
            return null;
        }

        return DB::transaction(function () use ($items): Review {
            /** @var Review $review */
            $review = Review::query()->create();

            foreach ($items as $position => $item) {
                ReviewItem::query()->create([
                    'review_id' => $review->id,
                    'position' => $position + 1,
                    'kind' => $item['kind'],
                    'item_identity' => $item['identity'],
                ]);
            }

            return $review;
        });
    }
}
