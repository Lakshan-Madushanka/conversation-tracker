<?php

declare(strict_types=1);

namespace App\Actions\CustomReviewAttempts;

use App\Enums\CustomReviewAttemptStatus;
use App\Enums\CustomReviewStatus;
use App\Models\CustomReview;
use App\Models\CustomReviewAttempt;
use App\Models\CustomReviewAttemptItem;
use App\Queries\CustomReviewAttemptList;
use App\Support\CustomReviewPractice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Starts an attempt at a Finished review, or resumes the one already running.
 *
 * The attempt is created In progress at once, with the next number counting
 * every attempt the review has ever started, and every snapshotted item is
 * written in the same transaction. A double press or a second tab waits on the
 * review's row and then finds the attempt that is already running, so one
 * start yields one attempt.
 *
 * Only starting is refused by the review's status. An attempt already running
 * is resumed whatever the review's status has become since, because a learner
 * halfway through the practice must never be cut short.
 *
 * A kind the review holds nothing usable of, because its items are archived,
 * is left out of the attempt and named in the notes that come back with it, so
 * the practice screen never looks shorter than the review without saying why.
 */
final readonly class StartCustomReviewAttempt
{
    public function __construct(private CustomReviewAttemptList $list) {}

    /**
     * @return array{attempt: CustomReviewAttempt|null, note: string|null, notices: list<string>}
     */
    public function handle(CustomReview $review): array
    {
        return DB::transaction(function () use ($review): array {
            /** @var CustomReview $locked */
            $locked = CustomReview::query()->lockForUpdate()->findOrFail($review->id);

            $running = CustomReviewAttempt::query()
                ->where('custom_review_id', $locked->id)
                ->where('status', CustomReviewAttemptStatus::InProgress->value)
                ->orderByDesc('number')
                ->orderByDesc('id')
                ->first();

            if ($running instanceof CustomReviewAttempt) {
                return ['attempt' => $running, 'note' => null, 'notices' => []];
            }

            if ($locked->status !== CustomReviewStatus::Finished) {
                return [
                    'attempt' => null,
                    'note' => CustomReviewPractice::note($locked->status, true),
                    'notices' => [],
                ];
            }

            $built = $this->list->for($locked);
            $items = $built['items'];

            if ($items === []) {
                return [
                    'attempt' => null,
                    'note' => CustomReviewPractice::NOT_AVAILABLE,
                    'notices' => [],
                ];
            }

            /** @var CustomReviewAttempt $attempt */
            $attempt = CustomReviewAttempt::query()->create([
                'custom_review_id' => $locked->id,
                'number' => $this->nextNumber($locked),
                'status' => CustomReviewAttemptStatus::InProgress,
                'started_at' => CarbonImmutable::now(),
            ]);

            $taken = CarbonImmutable::now();

            foreach ($items as $item) {
                CustomReviewAttemptItem::query()->create([
                    'custom_review_attempt_id' => $attempt->id,
                    'position' => $item['position'],
                    'kind' => $item['kind'],
                    'exercise' => $item['exercise'],
                    'item_identity' => $item['identity'],
                    'snapshot_text' => $item['snapshot_text'],
                    'snapshot' => $item['card'],
                    'topic' => $item['topic'],
                    'session_date' => $item['session_date'],
                    'source_session_id' => $item['source_session_id'],
                    'source_url' => $item['source_url'],
                    'snapshot_taken_at' => $taken,
                    'rating_key' => CustomReviewAttemptItem::ratingKeyFor(
                        $attempt->id,
                        $item['kind'],
                        $item['identity'],
                    ),
                ]);
            }

            return ['attempt' => $attempt, 'note' => null, 'notices' => $built['notices']];
        });
    }

    /** The next number, counting every attempt ever started. */
    private function nextNumber(CustomReview $review): int
    {
        return (int) CustomReviewAttempt::query()
            ->where('custom_review_id', $review->id)
            ->max('number') + 1;
    }
}
