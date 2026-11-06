<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CollectionItemMark;
use App\Queries\TableCollection;
use App\Support\CollectionMatch;
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
 * The note's own identity carries the learner's marks, so identical notes
 * never share a star, status or personal note.
 *
 * @phpstan-import-type NoteItem from TableCollection
 */
final class CollectionNoteResource extends JsonResource
{
    /**
     * @param  NoteItem  $item
     * @param  list<string>  $terms
     */
    public function __construct(
        array $item,
        public readonly ?CollectionItemMark $mark = null,
        public readonly bool $withNotes = false,
        public readonly array $terms = [],
        public readonly bool $inBasket = false,
    ) {
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
     *     entry_position: int,
     *     starred: bool,
     *     status: string|null,
     *     has_notes: bool,
     *     in_basket: bool,
     *     notes_html?: string,
     *     notes_version?: int,
     *     match: array{label: string, segments: list<array{text: string, match: bool}>}|null
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
            'starred' => $this->mark->starred ?? false,
            'status' => $this->mark?->status?->value,
            'has_notes' => $this->mark?->hasNotes() ?? false,
            'in_basket' => $this->inBasket,
            ...($this->withNotes ? $this->learnerNotes() : []),
            'match' => CollectionMatch::forNote($item, $this->mark, $this->terms),
        ];
    }

    /**
     * @return array{notes_html: string, notes_version: int}
     */
    private function learnerNotes(): array
    {
        return [
            'notes_html' => $this->mark->notes_html ?? '',
            'notes_version' => $this->mark?->exists ? $this->mark->notes_version : 0,
        ];
    }
}
