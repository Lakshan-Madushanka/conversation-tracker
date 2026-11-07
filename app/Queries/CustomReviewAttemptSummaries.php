<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CustomReviewAttemptStatus;
use App\Models\CustomReviewAttempt;
use Illuminate\Support\Facades\DB;

/**
 * The latest attempt facts for many reviews at once, for the listing cards.
 *
 * The card and the review's own history read the same rows, so they can never
 * disagree about the latest number, the previous status or the count. The
 * number of queries follows the page size, never the number of attempts: one
 * ranks the newest few attempts per review, one loads those attempts, and one
 * counts them.
 *
 * A Not started attempt keeps no number, so it is not part of any figure here;
 * it is only shown in a review's own history.
 *
 * @phpstan-type AttemptSummary array{
 *     attempt_count: int,
 *     latest: CustomReviewAttempt|null,
 *     previous_status: CustomReviewAttemptStatus|null
 * }
 */
final readonly class CustomReviewAttemptSummaries
{
    /** How many of a review's newest attempts are read for the previous status. */
    private const int RANKED_WINDOW = 3;

    /**
     * @param  list<int>  $reviewIds
     * @return array<int, AttemptSummary>
     */
    public function for(array $reviewIds): array
    {
        $reviewIds = array_values(array_unique(array_filter(
            $reviewIds,
            fn (int $reviewId): bool => $reviewId > 0,
        )));

        if ($reviewIds === []) {
            return [];
        }

        $ranked = DB::table('custom_review_attempts')
            ->select(['id', 'custom_review_id', 'number', 'status'])
            ->selectRaw(
                'ROW_NUMBER() OVER (PARTITION BY custom_review_id ORDER BY number DESC, id DESC) as rank_position',
            )
            ->whereIn('custom_review_id', $reviewIds)
            ->whereNotNull('number');

        $rows = DB::query()
            ->fromSub($ranked, 'ranked_attempts')
            ->where('rank_position', '<=', self::RANKED_WINDOW)
            ->orderBy('custom_review_id')
            ->orderBy('rank_position')
            ->get();

        $latestIds = [];
        $previousByReview = [];

        foreach ($rows as $row) {
            $reviewId = (int) $row->custom_review_id;

            if ((int) $row->rank_position === 1) {
                $latestIds[$reviewId] = (int) $row->id;

                continue;
            }

            $status = CustomReviewAttemptStatus::from((string) $row->status);

            if ($status === CustomReviewAttemptStatus::InProgress) {
                continue;
            }

            $previousByReview[$reviewId] ??= $status;
        }

        /** @var array<int, CustomReviewAttempt> $latest */
        $latest = CustomReviewAttempt::query()
            ->whereIn('id', array_values($latestIds))
            ->get()
            ->keyBy(fn (CustomReviewAttempt $attempt): int => (int) $attempt->id)
            ->all();

        /** @var array<int, int|string> $counts */
        $counts = DB::table('custom_review_attempts')
            ->select(['custom_review_id'])
            ->selectRaw('COUNT(*) as attempt_count')
            ->whereIn('custom_review_id', $reviewIds)
            ->whereNotNull('number')
            ->groupBy('custom_review_id')
            ->pluck('attempt_count', 'custom_review_id')
            ->all();

        $summaries = [];

        foreach ($reviewIds as $reviewId) {
            $summaries[$reviewId] = [
                'attempt_count' => (int) ($counts[$reviewId] ?? 0),
                'latest' => $latest[$latestIds[$reviewId] ?? 0] ?? null,
                'previous_status' => $previousByReview[$reviewId] ?? null,
            ];
        }

        return $summaries;
    }
}
