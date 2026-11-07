<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CustomReview;
use App\Support\CustomReviewPractice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * One custom review as a card on the listing.
 *
 * The item count, the status and the size tag come from the same summary the
 * picker and the review's page use, so the three can never word a review
 * differently.
 *
 * Attempts arrive in Step 4. The card already carries the fields that will
 * hold them, with the wording a review with no attempts has, so the card will
 * not need reshaping when they do.
 */
final class CustomReviewCardResource extends JsonResource
{
    public function __construct(
        CustomReview $resource,
        private readonly int $itemCount,
        private readonly bool $hasEligibleItem,
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
     *     attempt_number_label: string,
     *     previous_attempt_status: string|null,
     *     previous_attempt_label: string|null,
     *     attempt_summary_label: string,
     *     view_history_url: string|null,
     *     can_start: bool,
     *     practice_note: string|null
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

        return [
            ...$summary,
            'description_excerpt' => $this->excerpt($review->description),
            'created_at' => $review->created_at?->toIso8601String(),
            'attempt_count' => 0,
            'has_attempts' => false,
            'attempt_number_label' => 'No attempts yet',
            'previous_attempt_status' => null,
            'previous_attempt_label' => null,
            'attempt_summary_label' => 'No attempts yet',
            'view_history_url' => null,
            'can_start' => CustomReviewPractice::canStart(
                $review->status,
                $this->hasEligibleItem,
            ),
            'practice_note' => CustomReviewPractice::note(
                $review->status,
                $this->hasEligibleItem,
            ),
        ];
    }

    /** The description only as far as a card should show it. */
    private function excerpt(?string $description): ?string
    {
        $description = trim((string) $description);

        return $description === '' ? null : Str::limit($description, 160);
    }
}
