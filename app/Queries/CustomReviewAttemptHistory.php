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
 * Two reads cover the whole history however many attempts or items there are:
 * one for the attempts and one for their items. Nothing is loaded one attempt
 * at a time and nothing is written, so opening the history can never change an
 * attempt.
 *
 * @phpstan-type HistoryAttempt array{
 *     attempt: CustomReviewAttempt,
 *     items: Collection<int, CustomReviewAttemptItem>
 * }
 */
final class CustomReviewAttemptHistory
{
    /**
     * @return list<HistoryAttempt>
     */
    public function for(CustomReview $review): array
    {
        /** @var EloquentCollection<int, CustomReviewAttempt> $attempts */
        $attempts = CustomReviewAttempt::query()
            ->where('custom_review_id', $review->id)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();

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
}
