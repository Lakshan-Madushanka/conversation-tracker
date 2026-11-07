<?php

declare(strict_types=1);

namespace App\Actions\CustomReviewAttempts;

use App\Enums\CustomReviewAttemptStatus;
use App\Models\CustomReviewAttempt;
use Carbon\CarbonImmutable;

/**
 * Ends an attempt before every item has a rating, keeping what was given.
 *
 * Nothing happens until at least one item is rated, so an attempt with no
 * rating at all stays In progress (the learner has not practised yet). The
 * unrated items stay unrated; they are never filled in on the learner's behalf.
 */
final class FinishAttemptEarly
{
    public function handle(CustomReviewAttempt $attempt): CustomReviewAttempt
    {
        if ($attempt->isFinished()) {
            return $attempt;
        }

        if (! $attempt->items()->whereNotNull('rating')->exists()) {
            return $attempt;
        }

        $attempt->status = CustomReviewAttemptStatus::FinishedEarly;
        $attempt->finished_at = CarbonImmutable::now();
        $attempt->save();

        return $attempt;
    }
}
