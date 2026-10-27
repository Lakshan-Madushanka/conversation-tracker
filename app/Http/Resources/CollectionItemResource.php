<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One word in a collection, with every place it appeared.
 *
 * The list uses the newest spelling and the newest meaning and example that
 * hold anything. The panel uses the appearances to show every distinct meaning
 * and example and to link back to the session each one came from.
 */
final class CollectionItemResource extends JsonResource
{
    /**
     * @param  array{
     *     key: string,
     *     word: string,
     *     meaning: string,
     *     example: string,
     *     latest_at: CarbonImmutable,
     *     seen_count: int,
     *     appearances: list<array{
     *         session_id: int,
     *         session_date: CarbonImmutable|null,
     *         topic: string,
     *         question_position: int,
     *         entry_id: string|null,
     *         entry_position: int,
     *         meaning: string,
     *         example: string
     *     }>
     * }  $item
     */
    public function __construct(array $item)
    {
        parent::__construct($item);
    }

    /**
     * @return array{
     *     key: string,
     *     word: string,
     *     meaning: string,
     *     example: string,
     *     latest_session_date: string,
     *     seen_count: int,
     *     needs_attention: bool,
     *     appearances: list<array{
     *         session_id: int,
     *         session_date: string|null,
     *         topic: string,
     *         question_position: int,
     *         entry_id: string|null,
     *         entry_position: int,
     *         meaning: string,
     *         example: string
     *     }>
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array{
         *     key: string,
         *     word: string,
         *     meaning: string,
         *     example: string,
         *     latest_at: CarbonImmutable,
         *     seen_count: int,
         *     appearances: list<array{
         *         session_id: int,
         *         session_date: CarbonImmutable|null,
         *         topic: string,
         *         question_position: int,
         *         entry_id: string|null,
         *         entry_position: int,
         *         meaning: string,
         *         example: string
         *     }>
         * } $item
         */
        $item = $this->resource;

        return [
            'key' => $item['key'],
            'word' => $item['word'],
            'meaning' => $item['meaning'],
            'example' => $item['example'],
            'latest_session_date' => $item['latest_at']->toIso8601String(),
            'seen_count' => $item['seen_count'],
            'needs_attention' => $item['meaning'] === '',
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
                ],
                $item['appearances'],
            ),
        ];
    }
}
