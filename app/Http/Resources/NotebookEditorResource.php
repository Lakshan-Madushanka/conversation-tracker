<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\FeedbackType;
use App\Models\Notebook;
use App\Models\PracticeFeedbackEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * The notebook shaped for the board editor: metadata, the current version,
 * and the three boxes in the same row shapes the session editor uses.
 */
final class NotebookEditorResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     topic: array{id: int, name: string},
     *     description_html: string,
     *     version: int,
     *     last_changed_at: string|null,
     *     feedback: array<string, list<array{id: string, text: string, meaning: string, example_html: mixed}|array{id: string, html: string}>>
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var Notebook $notebook */
        $notebook = $this->resource;

        $notebook->loadMissing(['topic', 'entries']);

        $grouped = $notebook->entries->groupBy(
            fn (PracticeFeedbackEntry $entry): string => $entry->type->value,
        );

        $feedback = [];

        foreach ([FeedbackType::Vocabulary, FeedbackType::Phrase, FeedbackType::GrammarNote] as $type) {
            /** @var Collection<int, PracticeFeedbackEntry> $entries */
            $entries = $grouped->get($type->value, new Collection);

            $feedback[$type->value] = $this->feedbackPayload($type, $entries);
        }

        return [
            'id' => $notebook->id,
            'name' => $notebook->name,
            'topic' => [
                'id' => $notebook->topic->id,
                'name' => $notebook->topic->name,
            ],
            'description_html' => $notebook->description_html ?? '',
            'version' => $notebook->version,
            'last_changed_at' => $notebook->last_changed_at?->toIso8601String(),
            'feedback' => $feedback,
        ];
    }

    /**
     * One box shaped for the editor: table rows with their columns, one
     * formatted entry per grammar note.
     *
     * @param  Collection<int, PracticeFeedbackEntry>  $entries
     * @return list<array{id: string, text: string, meaning: string, example_html: string|list<array{id: string, text: string, html: string}>}|array{id: string, html: string}>
     */
    private function feedbackPayload(FeedbackType $type, Collection $entries): array
    {
        $payload = [];

        foreach ($entries as $entry) {
            $id = $entry->client_id ?? '';

            if ($type->isTable()) {
                $payload[] = [
                    'id' => $id,
                    'text' => $entry->content_text,
                    'meaning' => $entry->correction_text,
                    'example_html' => $entry->example_html,
                ];

                continue;
            }

            $payload[] = ['id' => $id, 'html' => $entry->content_html];
        }

        return $payload;
    }
}
