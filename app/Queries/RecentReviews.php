<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ReviewRating;
use App\Support\ReviewSettings;
use Illuminate\Support\Facades\DB;

/**
 * The reviews the learner recently ended, with what they actually rated.
 *
 * One grouped read: the sittings with a finish moment, newest first, each with
 * its ratings counted from the `ratings` rows themselves — so an undone rating
 * is gone, a re-rated item counts once with its final rating, and an item that
 * was skipped never appears in the tally. Joining the ratings is what drops a
 * sitting with nothing rated, so the list never shows an empty result.
 *
 * @phpstan-type RecentRow array{
 *     id: int,
 *     is_daily_review: bool,
 *     is_custom_review: bool,
 *     finished_at: string,
 *     rated: int,
 *     easy: int,
 *     okay: int,
 *     hard: int
 * }
 */
final class RecentReviews
{
    /**
     * @return list<RecentRow>
     */
    public function list(): array
    {
        $rows = DB::query()
            ->from('reviews')
            ->join('ratings', 'ratings.review_id', '=', 'reviews.id')
            ->select([
                'reviews.id',
                'reviews.is_daily_review',
                'reviews.is_custom_review',
                'reviews.finished_at',
            ])
            ->selectRaw('COUNT(ratings.id) as rated')
            ->selectRaw(
                'SUM(CASE WHEN ratings.rating = ? THEN 1 ELSE 0 END) as easy',
                [ReviewRating::Easy->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN ratings.rating = ? THEN 1 ELSE 0 END) as okay',
                [ReviewRating::Okay->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN ratings.rating = ? THEN 1 ELSE 0 END) as hard',
                [ReviewRating::Hard->value],
            )
            ->whereNotNull('reviews.finished_at')
            ->groupBy('reviews.id')
            ->orderByDesc('reviews.finished_at')
            ->orderByDesc('reviews.id')
            ->limit(ReviewSettings::RECENT_RESULTS)
            ->get();

        $results = [];

        foreach ($rows as $row) {
            $results[] = [
                'id' => (int) $row->id,
                'is_daily_review' => (bool) $row->is_daily_review,
                'is_custom_review' => (bool) $row->is_custom_review,
                'finished_at' => (string) $row->finished_at,
                'rated' => (int) $row->rated,
                'easy' => (int) $row->easy,
                'okay' => (int) $row->okay,
                'hard' => (int) $row->hard,
            ];
        }

        return $results;
    }
}
