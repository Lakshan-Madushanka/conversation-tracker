<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Queries\CustomReviewContents;
use App\Support\CustomReviewSourceUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One item in a custom review, shaped for the review's page.
 *
 * The words shown are read live from the session, never copied into the
 * review. `available` is false while every place the item came from is
 * archived, and `reason` says why an item cannot be used yet. `href` opens the original
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
     *     eligible: bool,
     *     reason: string|null,
     *     source_kind: 'session'|'notebook',
     *     topic: string,
     *     notebook: array{id: int, name: string, topic: string}|null,
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
            'eligible' => $item['eligible'],
            'reason' => $item['reason'],
            'source_kind' => $item['source']['source_kind'],
            'topic' => $item['source']['topic'],
            'notebook' => $item['source']['source_kind'] === 'notebook'
                ? [
                    'id' => (int) $item['source']['notebook_id'],
                    'name' => (string) $item['source']['notebook_name'],
                    'topic' => (string) $item['source']['topic'],
                ]
                : null,
            'session_date' => $item['source']['session_date']?->toIso8601String(),
            'href' => CustomReviewSourceUrl::of($item['source']),
        ];
    }
}
