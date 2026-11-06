<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Models\Review;
use App\Models\ReviewItem;
use App\Queries\TodaysReview;
use App\Support\ReviewFilters;
use App\Support\ReviewSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Starts a review from the items most worth revisiting right now.
 *
 * The list is fixed when the review starts, so a refresh, a bookmark or a
 * retry returns to the same sitting and to the next item not yet rated. The
 * items are named by identity only; their words are read again when shown.
 *
 * A review started from the day's suggestion is marked as the daily review,
 * so finishing it can say so warmly. "Review a bit more" starts the same
 * sitting without the daily mark, leaving out the items already rated today.
 * A custom review names its own scope through `App\Support\ReviewFilters` and
 * is marked as custom, so it can never be mistaken for the day's suggestion.
 */
final readonly class StartReview
{
    public function __construct(private TodaysReview $todaysReview) {}

    /**
     * Nothing reviewable means no review: the caller shows the friendly empty
     * state rather than an empty sitting.
     */
    public function handle(
        bool $daily = false,
        bool $excludingRatedToday = false,
        ?Request $request = null,
        ?ReviewFilters $filters = null,
        int $size = ReviewSettings::SIZE,
    ): ?Review {
        $suggestion = $this->todaysReview->suggest(
            size: $size,
            excludingRatedToday: $excludingRatedToday,
            request: $request,
            filters: $filters,
        );

        if ($suggestion->count() === 0) {
            return null;
        }

        return DB::transaction(function () use ($suggestion, $daily, $filters): Review {
            /** @var Review $review */
            $review = Review::query()->create([
                'is_daily_review' => $daily,
                'is_custom_review' => $filters instanceof ReviewFilters && $filters->custom,
            ]);

            foreach ($suggestion->items as $position => $item) {
                ReviewItem::query()->create([
                    'review_id' => $review->id,
                    'position' => $position + 1,
                    'kind' => $item['kind'],
                    'exercise' => $item['exercise'],
                    'item_identity' => $item['identity'],
                ]);
            }

            return $review;
        });
    }
}
