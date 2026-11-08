<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\CustomReviewAttemptStatus;
use App\Models\CustomReview;
use App\Models\CustomReviewAttempt;
use App\Support\CustomReviewAttemptLabel;
use App\Support\CustomReviewPractice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * One custom review as a card on the listing.
 *
 * The item count, the status and the size tag come from the same summary the
 * picker and the review's page use, so the three can never word a review
 * differently. The attempt figures come from the same bulk read the review's
 * history uses, so the card and the history always agree.
 *
 * The card only offers a start while the review is Finished with something to
 * use; an attempt already running is resumed instead, whatever the review's
 * status has become since.
 */
final class CustomReviewCardResource extends JsonResource
{
    public function __construct(
        CustomReview $resource,
        private readonly int $itemCount,
        private readonly int $eligibleCount,
        private readonly int $attemptCount,
        private readonly ?CustomReviewAttempt $latestAttempt,
        private readonly ?CustomReviewAttemptStatus $previousStatus,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     description: string|null,
     *     description_excerpt: string|null,
     *     status: string,
     *     status_label: string,
     *     item_count: int,
     *     item_count_label: string,
     *     too_many_items: bool,
     *     too_many_label: string|null,
     *     created_at: string|null,
     *     attempt_count: int,
     *     has_attempts: bool,
     *     in_progress: bool,
     *     attempt_number_label: string,
     *     previous_attempt_status: string|null,
     *     previous_attempt_label: string|null,
     *     attempt_summary_label: string,
     *     view_history_url: string|null,
     *     can_start: bool,
     *     eligible_count: int,
     *     start_label: string|null,
     *     start_url: string|null,
     *     resume_url: string|null,
     *     practice_note: string|null,
     *     delete_url: string
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var CustomReview $review */
        $review = $this->resource;

        $summary = CustomReviewSummaryResource::make(
            $review,
            $this->itemCount,
        )->toArray($request);

        $inProgress = $this->latestAttempt?->isInProgress() ?? false;
        $canStart = CustomReviewPractice::canStart(
            $review->status,
            $this->eligibleCount > 0,
        );
        $historyUrl = route('customReviews.show', ['customReview' => $review->id]).'#attempts';
        $resumeUrl = null;

        if ($this->latestAttempt instanceof CustomReviewAttempt && $this->latestAttempt->isInProgress()) {
            $resumeUrl = route('customReviews.attempts.show', [
                'customReview' => $review->id,
                'attempt' => $this->latestAttempt->id,
            ]);
        }

        return [
            ...$summary,
            'description_excerpt' => $this->excerpt($review->description),
            'created_at' => $review->created_at?->toIso8601String(),
            'attempt_count' => $this->attemptCount,
            'has_attempts' => $this->attemptCount > 0,
            'in_progress' => $inProgress,
            'attempt_number_label' => CustomReviewAttemptLabel::number($this->latestAttempt),
            'previous_attempt_status' => $this->previousStatus?->value,
            'previous_attempt_label' => CustomReviewAttemptLabel::previous($this->previousStatus),
            'attempt_summary_label' => CustomReviewAttemptLabel::summary(
                $this->attemptCount,
                $this->latestAttempt,
                $request,
            ),
            'view_history_url' => $this->attemptCount > 0 ? $historyUrl : null,
            'can_start' => $canStart,
            'eligible_count' => $this->eligibleCount,
            'start_label' => $canStart && ! $inProgress
                ? CustomReviewPractice::startLabel($this->eligibleCount)
                : null,
            'start_url' => $canStart && ! $inProgress
                ? route('customReviews.attempts.store', ['customReview' => $review->id])
                : null,
            'resume_url' => $resumeUrl,
            'practice_note' => CustomReviewPractice::note(
                $review->status,
                $this->eligibleCount > 0,
            ),
            // The card's own Delete asks first, then answers on the listing.
            'delete_url' => route('customReviews.destroy', ['customReviewId' => $review->id]),
        ];
    }

    /** The description only as far as a card should show it. */
    private function excerpt(?string $description): ?string
    {
        $description = trim((string) $description);

        return $description === '' ? null : Str::limit($description, 160);
    }
}
