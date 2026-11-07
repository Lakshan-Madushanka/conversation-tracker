<?php

declare(strict_types=1);

namespace App\Actions\CustomReviewAttempts;

use App\Enums\CustomReviewAttemptStatus;
use App\Models\CustomReviewAttempt;
use App\Models\CustomReviewAttemptItem;
use App\Queries\AttemptSections;
use Carbon\CarbonImmutable;

/**
 * Records that the learner has been through everything the attempt can still
 * show them.
 *
 * An item whose session has been archived since the attempt started is not met
 * again, so it does not hold the end up; the walk `AttemptSections` builds is
 * the one list the attempt is finished against. The moment the learner reached
 * the end is kept once; calling this again for the same attempt changes
 * nothing. Counts are never stored, so the summary always reads the ratings.
 */
final readonly class CompleteAttemptIfSettled
{
    public function __construct(private AttemptSections $sections) {}

    public function handle(CustomReviewAttempt $attempt): CustomReviewAttempt
    {
        $showing = $this->sections->walk($attempt)['items'];

        if ($showing->contains(fn (CustomReviewAttemptItem $item): bool => ! $item->isSettled())) {
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
