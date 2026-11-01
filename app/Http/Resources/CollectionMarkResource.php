<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CollectionItemMark;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The learner's marks for one collection item, shaped for the screen. The
 * notes travel as cleaned formatted text — reduced on the server to the
 * formatting the app supports — and with the version marker another tab's
 * changes are detected with.
 */
final class CollectionMarkResource extends JsonResource
{
    public function __construct(CollectionItemMark $mark)
    {
        parent::__construct($mark);
    }

    /**
     * @return array{
     *     starred: bool,
     *     status: string|null,
     *     has_notes: bool,
     *     notes_version: int,
     *     notes_html: string
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var CollectionItemMark $mark */
        $mark = $this->resource;

        return [
            'starred' => $mark->starred,
            'status' => $mark->status?->value,
            'has_notes' => $mark->hasNotes(),
            'notes_version' => $mark->notes_version,
            'notes_html' => $mark->notes_html,
        ];
    }
}
