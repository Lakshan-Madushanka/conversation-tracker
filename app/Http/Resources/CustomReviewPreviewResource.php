<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ReviewSettings;
use App\Support\ReviewSuggestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * How many items a custom review would hold right now, in the words the page
 * shows.
 *
 * The count, the mix and the estimate all come from the one suggestion the
 * started review is built from, so the preview can never promise a sitting the
 * review then turns out not to be. Nothing about it is stored: it is worked out
 * from the current data every time the choices change.
 */
final class CustomReviewPreviewResource extends JsonResource
{
    public function __construct(ReviewSuggestion $suggestion, public readonly int $size)
    {
        parent::__construct($suggestion);
    }

    /**
     * @return array{
     *     count: int,
     *     size: int,
     *     minutes: int,
     *     mix: string,
     *     comfortable: bool,
     *     kinds: int
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var ReviewSuggestion $suggestion */
        $suggestion = $this->resource;
        $perKind = $suggestion->perKind();

        // Nothing matching has no sitting to describe, so no estimate either.
        $empty = $suggestion->count() === 0;

        return [
            'count' => $suggestion->count(),
            'size' => $this->size,
            'minutes' => $empty ? 0 : $suggestion->minutes(),
            'mix' => $empty ? '' : $suggestion->mix(),
            'comfortable' => $suggestion->count() >= ReviewSettings::MIN_COMFORTABLE_MATCH,
            'kinds' => count($perKind),
        ];
    }
}
