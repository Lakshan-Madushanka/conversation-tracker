<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CustomReviewAttemptItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One item in an attempt's history, read only from the snapshot it kept.
 *
 * The words are the ones the attempt showed, never a newer version of the
 * item, and the link opens the reading page at the place it came from in a new
 * tab. Nothing here can change the attempt or the item.
 */
final class CustomReviewAttemptItemResource extends JsonResource
{
    public function __construct(CustomReviewAttemptItem $item)
    {
        parent::__construct($item);
    }

    /**
     * @return array{
     *     id: int,
     *     kind: string,
     *     kind_label: string,
     *     exercise: string,
     *     text: string,
     *     rating: string|null,
     *     rating_label: string|null,
     *     state: 'rated'|'skipped'|'not_rated',
     *     topic: string,
     *     session_date: string|null,
     *     href: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var CustomReviewAttemptItem $item */
        $item = $this->resource;

        $state = match (true) {
            $item->rating !== null => 'rated',
            $item->isSkipped() => 'skipped',
            default => 'not_rated',
        };

        return [
            'id' => (int) $item->id,
            'kind' => $item->kind->value,
            'kind_label' => $item->kind->customReviewLabel(),
            'exercise' => $item->exercise,
            'text' => $item->snapshot_text,
            'rating' => $item->rating?->value,
            'rating_label' => $item->rating?->label(),
            'state' => $state,
            'topic' => $item->topic,
            'session_date' => $item->session_date?->toIso8601String(),
            'href' => $item->source_url,
        ];
    }
}
