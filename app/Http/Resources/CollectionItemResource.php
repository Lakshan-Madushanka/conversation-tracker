<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Queries\TableCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One word or phrase in a collection, with every place it appeared.
 *
 * The list uses the newest spelling and the newest meaning and example that
 * hold anything. The panel uses the appearances to show every distinct meaning
 * and example and to link back to the session each one came from. Whether the
 * item needs attention — and which calm reason it gives — is decided by the
 * shared rule in `App\Support\RowAttention`, in one place for vocabulary and
 * phrases.
 *
 * @phpstan-import-type GroupItem from TableCollection
 */
final class CollectionItemResource extends JsonResource
{
    /**
     * @param  GroupItem  $item
     */
    public function __construct(array $item)
    {
        parent::__construct($item);
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     meaning: string,
     *     example: string,
     *     example_html: string,
     *     latest_session_date: string,
     *     seen_count: int,
     *     needs_attention: bool,
     *     attention_reason: string|null,
     *     appearances: list<array{
     *         session_id: int,
     *         session_date: string|null,
     *         topic: string,
     *         question_position: int,
     *         entry_id: string|null,
     *         entry_position: int,
     *         meaning: string,
     *         example: string,
     *         example_html: string
     *     }>
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var GroupItem $item */
        $item = $this->resource;

        return [
            'key' => $item['key'],
            'title' => $item['title'],
            'meaning' => $item['meaning'],
            'example' => $item['example'],
            'example_html' => $item['example_html'],
            'latest_session_date' => $item['latest_at']->toIso8601String(),
            'seen_count' => $item['seen_count'],
            'needs_attention' => $item['attention'] !== null,
            'attention_reason' => $item['attention'],
            'appearances' => array_map(
                fn (array $appearance): array => [
                    'session_id' => $appearance['session_id'],
                    'session_date' => $appearance['session_date']?->toIso8601String(),
                    'topic' => $appearance['topic'],
                    'question_position' => $appearance['question_position'],
                    'entry_id' => $appearance['entry_id'],
                    'entry_position' => $appearance['entry_position'],
                    'meaning' => $appearance['meaning'],
                    'example' => $appearance['example'],
                    'example_html' => $appearance['example_html'],
                ],
                $item['appearances'],
            ),
        ];
    }
}
