<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CustomReviewSort;
use App\Models\CustomReview;
use App\Support\CustomReviewFilters;
use App\Support\SearchText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

/**
 * The custom reviews that belong on the listing, narrowed by the learner's
 * search, statuses and chosen order.
 *
 * This is a read-only view. Nothing here repairs a stale reference, writes a
 * status or touches an item: the listing shows the reviews as they stand and
 * leaves every other page alone.
 */
final readonly class CustomReviewsListing
{
    /** How many reviews the listing loads at a time. */
    public const int PAGE_SIZE = 20;

    /**
     * One page of reviews, in the chosen order. The cursor continues from the
     * last review shown, not from a page number, so a review created while the
     * list is open can never make a later page repeat or skip one.
     *
     * @return CursorPaginator<int, CustomReview>
     */
    public function paginate(
        CustomReviewFilters $filters,
        ?string $cursor = null,
        int $perPage = self::PAGE_SIZE,
    ): CursorPaginator {
        $query = $this->query($filters);

        $this->applySort($query, $filters->sort());

        return $query->cursorPaginate(
            perPage: max(1, min(100, $perPage)),
            cursor: $cursor,
        );
    }

    /** How many reviews match the search and statuses. */
    public function total(CustomReviewFilters $filters): int
    {
        return $this->query($filters)->count();
    }

    /** How many reviews exist, whatever the listing is narrowed to. */
    public function allTotal(): int
    {
        return CustomReview::query()->count();
    }

    /**
     * @return Builder<CustomReview>
     */
    private function query(CustomReviewFilters $filters): Builder
    {
        $query = CustomReview::query();
        $terms = $filters->searchTerms();

        if ($terms !== []) {
            SearchText::whereAll($query, 'custom_reviews.search_text', $terms);
        }

        if ($filters->hasStatuses()) {
            $query->whereIn('status', $filters->statusValues());
        }

        return $query;
    }

    /**
     * @param  Builder<CustomReview>  $query
     */
    private function applySort(Builder $query, CustomReviewSort $sort): void
    {
        match ($sort) {
            CustomReviewSort::Name => $query
                ->orderByRaw('LOWER(name) ASC')
                ->orderBy('id'),
            CustomReviewSort::Created => $query
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            // The latest attempt's start, joined once for the whole page so
            // the cursor can continue from the same value it ended on. A
            // review never attempted sorts after every review that has been.
            default => $query
                ->leftJoinSub(
                    // Every review gets a row, so the cursor always compares a
                    // real value; never attempted sorts after every attempt.
                    DB::table('custom_reviews as review_clock')
                        ->leftJoin(
                            'custom_review_attempts as attempt_clock',
                            'attempt_clock.custom_review_id',
                            '=',
                            'review_clock.id',
                        )
                        ->selectRaw("review_clock.id as custom_review_id, coalesce(max(attempt_clock.started_at), '1970-01-01 00:00:00') as last_attempted_at")
                        ->groupBy('review_clock.id'),
                    'latest_attempts',
                    'latest_attempts.custom_review_id',
                    '=',
                    'custom_reviews.id',
                )
                ->select('custom_reviews.*', 'latest_attempts.last_attempted_at')
                ->orderByDesc('last_attempted_at')
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
        };
    }
}
