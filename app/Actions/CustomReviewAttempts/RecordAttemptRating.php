<?php

declare(strict_types=1);

namespace App\Actions\CustomReviewAttempts;

use App\Enums\CustomReviewAttemptStatus;
use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use App\Models\CustomReviewAttempt;
use App\Models\CustomReviewAttemptItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Saves one item's rating on the attempt, straight away and exactly once.
 *
 * The rating lives on the attempt's item row, never on a Review rating,
 * schedule or mark, so nothing else in the app can read it. The row's unique
 * rating key is written when the attempt starts, so a double tap, a retried
 * request or the same request from two tabs updates the same row instead of
 * adding another. Repeating the same choice changes nothing.
 *
 * Reaching the end of the attempt is recorded in the same transaction, so a
 * completed attempt is never left In progress.
 */
final readonly class RecordAttemptRating
{
    public function __construct(private CompleteAttemptIfSettled $complete) {}

    public function handle(
        CustomReviewAttempt $attempt,
        FeedbackType $kind,
        string $itemIdentity,
        ReviewRating $rating,
    ): CustomReviewAttempt {
        return DB::transaction(function () use ($attempt, $kind, $itemIdentity, $rating): CustomReviewAttempt {
            /** @var CustomReviewAttempt $locked */
            $locked = CustomReviewAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            // A shell that was created but never begun takes its number and
            // place in the count the moment the learner rates something in it.
            if ($locked->isNotStarted()) {
                $locked->number = $this->nextNumber($locked);
                $locked->status = CustomReviewAttemptStatus::InProgress;
                $locked->started_at ??= CarbonImmutable::now();
                $locked->save();
            }

            $item = $this->item($locked, $kind, $itemIdentity);

            if ($item->rating !== $rating) {
                $item->rating = $rating;
                $item->rated_at = CarbonImmutable::now();
                $item->skipped_at = null;
                $item->save();
            }

            return $this->complete->handle($locked);
        });
    }

    /** The next number, counting every attempt the review has ever started. */
    private function nextNumber(CustomReviewAttempt $attempt): int
    {
        return (int) CustomReviewAttempt::query()
            ->where('custom_review_id', $attempt->custom_review_id)
            ->max('number') + 1;
    }

    private function item(
        CustomReviewAttempt $attempt,
        FeedbackType $kind,
        string $itemIdentity,
    ): CustomReviewAttemptItem {
        /** @var CustomReviewAttemptItem $item */
        $item = CustomReviewAttemptItem::query()
            ->where('custom_review_attempt_id', $attempt->id)
            ->where('kind', $kind->value)
            ->where('item_identity', $itemIdentity)
            ->firstOrFail();

        return $item;
    }
}
