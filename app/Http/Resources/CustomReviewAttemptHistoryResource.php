<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CustomReviewAttempt;
use App\Models\CustomReviewAttemptItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * One attempt as its review's history lists it: its number, when it ran, how it
 * ended, and the counts read from its own ratings.
 *
 * The items come from the same bulk read as every other attempt's, so the
 * history stays two queries however long it grows.
 */
final class CustomReviewAttemptHistoryResource extends JsonResource
{
    /**
     * @param  Collection<int, CustomReviewAttemptItem>  $items
     */
    public function __construct(
        CustomReviewAttempt $attempt,
        private readonly Collection $items,
    ) {
        parent::__construct($attempt);
    }

    /**
     * @return array{
     *     id: int,
     *     number: int|null,
     *     number_label: string,
     *     status: string,
     *     status_label: string,
     *     started_at: string|null,
     *     finished_at: string|null,
     *     counts: array{hard: int, okay: int, easy: int, skipped: int, rated: int, unrated: int},
     *     items: list<array<string, mixed>>
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var CustomReviewAttempt $attempt */
        $attempt = $this->resource;

        $counts = ['hard' => 0, 'okay' => 0, 'easy' => 0, 'skipped' => 0, 'rated' => 0, 'unrated' => 0];

        foreach ($this->items as $item) {
            if ($item->rating !== null) {
                $counts['rated'] += 1;
                $counts[$item->rating->value] += 1;

                continue;
            }

            if ($item->isSkipped()) {
                $counts['skipped'] += 1;

                continue;
            }

            $counts['unrated'] += 1;
        }

        return [
            'id' => (int) $attempt->id,
            'number' => $attempt->number,
            'number_label' => $attempt->number === null
                ? 'Not started'
                : 'Attempt '.$attempt->number,
            'status' => $attempt->status->value,
            'status_label' => $attempt->status->label(),
            'started_at' => $attempt->started_at?->toIso8601String(),
            'finished_at' => $attempt->finished_at?->toIso8601String(),
            'counts' => $counts,
            'items' => array_values(array_map(
                fn (CustomReviewAttemptItem $item): array => CustomReviewAttemptItemResource::make($item)->resolve(),
                $this->items->all(),
            )),
        ];
    }
}
