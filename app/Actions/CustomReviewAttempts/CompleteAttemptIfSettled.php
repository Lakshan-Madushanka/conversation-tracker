<?php

declare(strict_types=1);

namespace App\Actions\CustomReviewAttempts;

use App\Enums\CustomReviewAttemptStatus;
use App\Models\CustomReviewAttempt;
use Carbon\CarbonImmutable;

/**
 * Records that every item in an attempt is rated or skipped.
 *
 * The moment the learner reached the end is kept once; calling this again for
 * the same attempt changes nothing. Counts are never stored, so the summary
 * always reads the ratings.
 */
final class CompleteAttemptIfSettled
{
    public function handle(CustomReviewAttempt $attempt): CustomReviewAttempt
    {
        $unsettled = $attempt->items()
            ->whereNull('rating')
            ->whereNull('skipped_at')
            ->exists();

        if ($unsettled) {
            return $attempt;
        }

        if ($attempt->status === CustomReviewAttemptStatus::Completed) {
            return $attempt;
        }

        $attempt->status = CustomReviewAttemptStatus::Completed;
        $attempt->finished_at ??= CarbonImmutable::now();
        $attempt->save();

        return $attempt;
    }
}
