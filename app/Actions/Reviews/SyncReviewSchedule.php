<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Enums\FeedbackType;
use App\Models\Rating;
use App\Models\ReviewSchedule;
use App\Support\LearnerDay;
use App\Support\ReviewSchedule as ReviewScheduleRules;
use App\Support\ScheduleState;

/**
 * Recompute one item's schedule from its rating history and store it.
 *
 * The ratings are the source of truth, so this reads the whole history of
 * the item, applies the schedule rule and writes the one stored row (or
 * removes it when no ratings remain). It is deterministic and safe to
 * repeat, and it is called in the same transaction as the rating change it
 * follows, so the stored schedule can never disagree with the history.
 */
final readonly class SyncReviewSchedule
{
    public function __construct(private LearnerDay $day) {}

    public function handle(FeedbackType $kind, string $identity): void
    {
        $ratings = Rating::query()
            ->where('kind', $kind->value)
            ->where('item_identity', $identity)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['rating', 'created_at']);

        $history = [];

        foreach ($ratings as $rating) {
            $history[] = [
                'rating' => $rating->rating,
                'day' => $this->day->dayOf($rating->created_at),
            ];
        }

        $state = ReviewScheduleRules::rebuild($history);

        if (! $state instanceof ScheduleState) {
            ReviewSchedule::query()
                ->where('kind', $kind->value)
                ->where('item_identity', $identity)
                ->delete();

            return;
        }

        ReviewSchedule::query()->upsert(
            [
                [
                    'kind' => $kind->value,
                    'item_identity' => $identity,
                    'due_on' => $state->dueOn->toDateString(),
                    'last_rated_on' => $state->lastRatedOn->toDateString(),
                    'last_rating' => $state->lastRating->value,
                    'rated_count' => $state->ratedCount,
                    'easy_run' => $state->easyRun,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            ['kind', 'item_identity'],
            ['due_on', 'last_rated_on', 'last_rating', 'rated_count', 'easy_run', 'updated_at'],
        );
    }
}
