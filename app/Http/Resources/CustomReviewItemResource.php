<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Queries\CustomReviewContents;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One item in a custom review, shaped for the review's page.
 *
 * The words shown are read live from the session, never copied into the
 * review. `available` is false while every place the item came from is
 * archived; the item stays listed and says so. `href` opens the original
 * session at the item in a new tab, with the same target the reading page
 * already understands.
 *
 * @phpstan-import-type CustomReviewItemData from CustomReviewContents
 */
final class CustomReviewItemResource extends JsonResource
{
    /**
     * @param  CustomReviewItemData  $item
     */
    public function __construct(array $item)
    {
        parent::__construct($item);
    }

    /**
     * @return array{
     *     id: string,
     *     kind: string,
     *     identity: string,
     *     title: string,
     *     correction: string,
     *     correction_html: string,
     *     available: bool,
     *     topic: string,
     *     session_date: string|null,
     *     href: string
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var CustomReviewItemData $item */
        $item = $this->resource;

        return [
            'id' => $item['id'],
            'kind' => $item['kind'],
            'identity' => $item['identity'],
            'title' => $item['title'],
            'correction' => $item['correction'],
            'correction_html' => $item['correction_html'],
            'available' => $item['available'],
            'topic' => $item['source']['topic'],
            'session_date' => $item['source']['session_date']?->toIso8601String(),
            'href' => $this->href($item),
        ];
    }

    /**
     * @param  CustomReviewItemData  $item
     */
    private function href(array $item): string
    {
        $source = $item['source'];

        $query = [
            'from' => 'review',
            'question' => (string) $source['question_position'],
            'field' => $source['field'],
        ];

        if ($source['field'] === 'entry') {
            $query['type'] = (string) $source['type'];
            $query['position'] = (string) $source['entry_position'];

            if ($source['entry_id'] !== null) {
                $query['entry'] = $source['entry_id'];
            }
        }

        return route('sessions.show', [
            'session' => $source['session_id'],
            ...$query,
        ]);
    }
}
