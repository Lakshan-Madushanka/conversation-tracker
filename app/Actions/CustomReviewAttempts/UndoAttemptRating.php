<?php

declare(strict_types=1);

namespace App\Actions\CustomReviewAttempts;

use App\Enums\CustomReviewAttemptStatus;
use App\Enums\FeedbackType;
use App\Models\CustomReviewAttempt;
use App\Models\CustomReviewAttemptItem;
use Illuminate\Support\Facades\DB;

/**
 * Takes back the attempt's rating for one item, leaving every other rating and
 * the item itself untouched.
 *
 * If the attempt had already ended, removing a rating reopens it as In
 * progress, so the counts and the status can never disagree and the learner can
 * finish again when they are ready.
 */
final class UndoAttemptRating
{
    public function handle(
        CustomReviewAttempt $attempt,
        FeedbackType $kind,
        string $itemIdentity,
    ): CustomReviewAttempt {
        return DB::transaction(function () use ($attempt, $kind, $itemIdentity): CustomReviewAttempt {
            /** @var CustomReviewAttempt $locked */
            $locked = CustomReviewAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            /** @var CustomReviewAttemptItem|null $item */
            $item = CustomReviewAttemptItem::query()
                ->where('custom_review_attempt_id', $locked->id)
                ->where('kind', $kind->value)
                ->where('item_identity', $itemIdentity)
                ->first();

            if ($item instanceof CustomReviewAttemptItem && $item->rating !== null) {
                $item->rating = null;
                $item->rated_at = null;
                $item->save();

                if ($locked->isFinished()) {
                    $locked->status = CustomReviewAttemptStatus::InProgress;
                    $locked->finished_at = null;
                    $locked->save();
                }
            }

            return $locked;
        });
    }
}
