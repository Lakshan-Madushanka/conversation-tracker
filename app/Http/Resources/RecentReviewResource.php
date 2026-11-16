<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Queries\RecentReviews;
use App\Support\CustomReviewAttemptLabel;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One recent result on Review Home: when it ended, what it was, and how the
 * learner rated it.
 *
 * A sitting and an attempt share the row; an attempt alone names the review
 * it belonged to. The finish moment leaves as the exact instant it happened,
 * so the page can show it as the learner's own local date. The counts are the
 * ones read from the saved ratings, never a separate tally. A result whose
 * items all live in one notebook carries that notebook as its source.
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
     *     key: string,
     *     id: int,
     *     type: string,
     *     type_label: string,
     *     title: string|null,
     *     finished_at: string,
     *     rated: int,
     *     counts: array{hard: int, okay: int, easy: int},
     *     url: string,
     *     source: array{label: string, url: string}|null
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var RecentRow $row */
        $row = $this->resource;

        $custom = $row['type'] === 'custom';

        return [
            'key' => $row['key'],
            'id' => $row['id'],
            'type' => $row['type'],
            'type_label' => $custom ? 'Custom review' : 'Daily review',
            'title' => $row['review_name'] === null
                ? null
                : CustomReviewAttemptLabel::headingForName($row['review_name'], $row['attempt_number']),
            'finished_at' => CarbonImmutable::parse($row['finished_at'], 'UTC')->toISOString(),
            'rated' => $row['rated'],
            'counts' => [
                'hard' => $row['hard'],
                'okay' => $row['okay'],
                'easy' => $row['easy'],
            ],
            'url' => $this->url($row),
            'source' => $row['source'] === null
                ? null
                : [
                    'label' => $row['source']['name'],
                    'url' => route('notebooks.show', ['notebook' => $row['source']['id']]),
                ],
        ];
    }

    /**
     * @param  RecentRow  $row
     */
    private function url(array $row): string
    {
        if ($row['custom_review_id'] === null) {
            return route('review.results', ['review' => $row['id']]);
        }

        return route('customReviews.attempts.show', [
            'customReview' => $row['custom_review_id'],
            'attempt' => $row['id'],
        ]);
    }
}
