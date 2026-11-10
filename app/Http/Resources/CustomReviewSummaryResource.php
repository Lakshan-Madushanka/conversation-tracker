<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CustomReview;
use App\Support\CustomReviewKinds;
use App\Support\CustomReviewRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A review by what tells it apart from the others: its name, its status, and
 * how many items it holds.
 *
 * The picker lists the reviews this way, because several can be open at once;
 * a confirmation names the review the items went into in the same words; and a
 * review's own page header shows the same three things.
 *
 * The count is read by the caller and passed in, so this only shapes it.
 */
final class CustomReviewSummaryResource extends JsonResource
{
    public function __construct(
        CustomReview $resource,
        private readonly int $itemCount,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     description: string|null,
     *     status: string,
     *     status_label: string,
     *     allowed_kinds: list<string>|null,
     *     allowed_kinds_label: string,
     *     item_count: int,
     *     item_count_label: string,
     *     too_many_items: bool,
     *     too_many_label: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var CustomReview $review */
        $review = $this->resource;
        $itemCountLabel = $this->itemCount === 1
            ? '1 item'
            : $this->itemCount.' items';
        $tooMany = CustomReviewRules::isTooMany($this->itemCount);

        return [
            'id' => (int) $review->id,
            'name' => (string) $review->name,
            'description' => $review->description,
            'status' => $review->status->value,
            'status_label' => $review->status->label(),
            'allowed_kinds' => $review->allowed_kinds,
            'allowed_kinds_label' => CustomReviewKinds::label($review->allowed_kinds),
            'item_count' => $this->itemCount,
            'item_count_label' => $itemCountLabel,
            'too_many_items' => $tooMany,
            'too_many_label' => $tooMany
                ? 'Too Many Items · '.$itemCountLabel
                : null,
        ];
    }
}
