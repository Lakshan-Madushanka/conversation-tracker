<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CollectionItemMark;
use App\Queries\TableCollection;
use App\Support\CollectionMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One mistake in the My mistakes collection, with every place it appeared.
 *
 * The list shows the words of the most recent appearance: what was said, the
 * correction with only its safe bold and italic kept, and the most recent
 * reason that holds anything. The panel uses the appearances to show every
 * distinct reason with its sessions and to link back to each place the
 * mistake was seen. Whether the mistake is worth practicing is decided by
 * the named threshold the grouping keeps, and whether it needs attention —
 * and which calm reason it gives — is decided by the shared rule in
 * `App\Support\RowAttention`.
 *
 * The learner's star, status and notes belong to the normalised pair, so every
 * place the mistake was seen shows the same marks.
 *
 * @phpstan-import-type MistakeItem from TableCollection
 */
final class CollectionMistakeResource extends JsonResource
{
    /**
     * @param  MistakeItem  $item
     * @param  list<string>  $terms
     */
    public function __construct(
        array $item,
        public readonly ?CollectionItemMark $mark = null,
        public readonly bool $withNotes = false,
        public readonly array $terms = [],
    ) {
        parent::__construct($item);
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     correction: string,
     *     correction_html: string,
     *     reason: string,
     *     latest_session_date: string,
     *     seen_count: int,
     *     worth_practicing: bool,
     *     needs_attention: bool,
     *     attention_reason: string|null,
     *     starred: bool,
     *     status: string|null,
     *     has_notes: bool,
     *     notes_html?: string,
     *     notes_version?: int,
     *     match: array{label: string, segments: list<array{text: string, match: bool}>}|null,
     *     appearances: list<array{
     *         session_id: int,
     *         session_date: string|null,
     *         topic: string,
     *         question_position: int,
     *         entry_id: string|null,
     *         entry_position: int,
     *         said: string,
     *         correction: string,
     *         correction_html: string,
     *         reason: string
     *     }>
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var MistakeItem $item */
        $item = $this->resource;

        return [
            'key' => $item['key'],
            'title' => $item['title'],
            'correction' => $item['correction'],
            'correction_html' => $item['correction_html'],
            'reason' => $item['reason'],
            'latest_session_date' => $item['latest_at']->toIso8601String(),
            'seen_count' => $item['seen_count'],
            'worth_practicing' => $item['worth_practicing'],
            'needs_attention' => $item['attention'] !== null,
            'attention_reason' => $item['attention'],
            'starred' => $this->mark->starred ?? false,
            'status' => $this->mark?->status?->value,
            'has_notes' => $this->mark?->hasNotes() ?? false,
            ...($this->withNotes ? $this->learnerNotes() : []),
            'match' => CollectionMatch::forMistake($item, $this->mark, $this->terms),
            'appearances' => array_map(
                fn (array $appearance): array => [
                    'session_id' => $appearance['session_id'],
                    'session_date' => $appearance['session_date']?->toIso8601String(),
                    'topic' => $appearance['topic'],
                    'question_position' => $appearance['question_position'],
                    'entry_id' => $appearance['entry_id'],
                    'entry_position' => $appearance['entry_position'],
                    'said' => $appearance['said'],
                    'correction' => $appearance['correction'],
                    'correction_html' => $appearance['correction_html'],
                    'reason' => $appearance['reason'],
                ],
                $item['appearances'],
            ),
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
