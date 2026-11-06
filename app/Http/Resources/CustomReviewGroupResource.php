<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One collection's items in a custom review, with the heading and count the
 * review's page shows. Empty groups never reach the page.
 */
final class CustomReviewGroupResource extends JsonResource
{
    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     count: int,
     *     items: list<array<string, mixed>>
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array{key: string, label: string, count: int, items: list<array<string, mixed>>} $group */
        $group = $this->resource;

        return [
            'key' => $group['key'],
            'label' => $group['label'],
            'count' => $group['count'],
            'items' => array_map(
                fn (array $item): array => CustomReviewItemResource::make($item)->resolve(),
                $group['items'],
            ),
        ];
    }
}
