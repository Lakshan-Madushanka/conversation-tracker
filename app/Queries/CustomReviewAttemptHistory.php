<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\CustomReview;
use App\Models\CustomReviewAttempt;
use App\Models\CustomReviewAttemptItem;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Every attempt at one review, newest first, with the items each one kept.
 *
 * Two reads cover a whole page however many attempts or items it holds: one
 * for the attempts and one for their items. Nothing is loaded one attempt at a
 * time and nothing is written, so opening the history can never change an
 * attempt.
 *
 * A page is bounded by the caller rather than read whole, because a history
 * can grow forever. The page a named attempt sits on is answered from a count
 * of the attempts that come before it, so an open row survives a reload even
 * when it sits beyond the first page.
 *
 * @phpstan-type HistoryAttempt array{
 *     attempt: CustomReviewAttempt,
 *     items: Collection<int, CustomReviewAttemptItem>
 * }
 */
final class CustomReviewAttemptHistory
{
    /** How many attempts one page of history holds. */
    public const int PER_PAGE = 10;

    /**
     * @return list<HistoryAttempt>
     */
    public function for(CustomReview $review, ?int $limit = null, int $offset = 0): array
    {
        $query = CustomReviewAttempt::query()
            ->where('custom_review_id', $review->id)
            ->orderByDesc('started_at')
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->offset($offset)->limit($limit);
        }

        /** @var EloquentCollection<int, CustomReviewAttempt> $attempts */
        $attempts = $query->get();

        if ($attempts->isEmpty()) {
            return [];
        }

        /** @var Collection<int, Collection<int, CustomReviewAttemptItem>> $items */
        $items = CustomReviewAttemptItem::query()
            ->whereIn('custom_review_attempt_id', $attempts->modelKeys())
            ->orderBy('position')
            ->get()
            ->groupBy('custom_review_attempt_id');

        $history = [];

        foreach ($attempts as $attempt) {
            $history[] = [
                'attempt' => $attempt,
                'items' => $items->get((int) $attempt->id, collect()),
            ];
        }

        return $history;
    }

    /** How many attempts the review's whole history holds. */
    public function countFor(CustomReview $review): int
    {
        return CustomReviewAttempt::query()
            ->where('custom_review_id', $review->id)
            ->count();
    }

    /** The one-based page a named attempt sits on, in the history's order. */
    public function pageOf(CustomReview $review, CustomReviewAttempt $attempt): int
    {
        $newer = CustomReviewAttempt::query()
            ->where('custom_review_id', $review->id)
            ->where(function ($query) use ($attempt): void {
                if ($attempt->started_at === null) {
                    $query->whereNotNull('started_at')
                        ->orWhere(fn ($inner) => $inner
                            ->whereNull('started_at')
                            ->where('id', '>', $attempt->id));

                    return;
                }

                $query->where('started_at', '>', $attempt->started_at)
                    ->orWhere(fn ($inner) => $inner
                        ->where('started_at', $attempt->started_at)
                        ->where('id', '>', $attempt->id));
            })
            ->count();

        return intdiv($newer, self::PER_PAGE) + 1;
    }
}
