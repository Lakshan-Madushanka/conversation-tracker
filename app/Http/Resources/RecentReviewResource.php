<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Queries\RecentReviews;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One recent result on Review Home: when the sitting ended, what it was, and
 * how the learner rated it.
 *
 * The finish moment leaves as the exact instant it happened, so the page can
 * show it as the learner's own local date. The counts are the ones read from
 * the ratings, never a separate tally.
 *
 * @phpstan-import-type RecentRow from RecentReviews
 */
final class RecentReviewResource extends JsonResource
{
    /**
     * @param  RecentRow  $row
     */
    public function __construct(array $row)
    {
        parent::__construct($row);
    }

    /**
     * @return array{
     *     id: int,
     *     type: string,
     *     type_label: string,
     *     finished_at: string,
     *     rated: int,
     *     counts: array{hard: int, okay: int, easy: int},
     *     url: string
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var RecentRow $row */
        $row = $this->resource;

        $custom = $row['is_custom_review'];

        return [
            'id' => $row['id'],
            'type' => $custom ? 'custom' : 'daily',
            'type_label' => $custom ? 'Custom review' : 'Daily review',
            'finished_at' => CarbonImmutable::parse($row['finished_at'], 'UTC')->toISOString(),
            'rated' => $row['rated'],
            'counts' => [
                'hard' => $row['hard'],
                'okay' => $row['okay'],
                'easy' => $row['easy'],
            ],
            'url' => route('review.results', ['review' => $row['id']]),
        ];
    }
}
