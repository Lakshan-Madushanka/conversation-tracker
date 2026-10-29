<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Queries\TableCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One grammar note in its collection, as its own item.
 *
 * The list shows a short plain-text preview, worked out on the server from
 * the note's own plain text. The panel shows the full note, already reduced
 * again to the formatting the app supports, so the saved text is never put
 * into the page as it was received.
 *
 * @phpstan-import-type NoteItem from TableCollection
 */
final class CollectionNoteResource extends JsonResource
{
    /**
     * @param  NoteItem  $item
     */
    public function __construct(array $item)
    {
        parent::__construct($item);
    }

    /**
     * @return array{
     *     key: string,
     *     text: string,
     *     preview: string,
     *     html: string,
     *     latest_session_date: string,
     *     session_id: int,
     *     session_date: string|null,
     *     topic: string,
     *     question_position: int,
     *     entry_id: string|null,
     *     entry_position: int
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var NoteItem $item */
        $item = $this->resource;

        return [
            'key' => $item['key'],
            'text' => $item['text'],
            'preview' => $item['preview'],
            'html' => $item['html'],
            'latest_session_date' => $item['latest_at']->toIso8601String(),
            'session_id' => $item['session_id'],
            'session_date' => $item['session_date']?->toIso8601String(),
            'topic' => $item['topic'],
            'question_position' => $item['question_position'],
            'entry_id' => $item['entry_id'],
            'entry_position' => $item['entry_position'],
        ];
    }
}
