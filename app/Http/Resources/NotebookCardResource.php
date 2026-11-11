<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Notebook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One notebook on the listing: its name, topic, plain-text description,
 * last-updated time and the counts the listing query aggregated.
 */
final class NotebookCardResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, topic: string, description: string, last_updated_at: string|null, counts: array{words: int, phrases: int, grammar_notes: int}}
     */
    public function toArray(Request $request): array
    {
        /** @var Notebook $notebook */
        $notebook = $this->resource;

        $notebook->loadMissing('topic');

        return [
            'id' => $notebook->id,
            'name' => $notebook->name,
            'topic' => $notebook->topic->name,
            'description' => $notebook->description_text ?? '',
            'last_updated_at' => ($notebook->last_changed_at ?? $notebook->updated_at)?->toIso8601String(),
            'counts' => [
                'words' => (int) $notebook->getAttribute('words_count'),
                'phrases' => (int) $notebook->getAttribute('phrases_count'),
                'grammar_notes' => (int) $notebook->getAttribute('grammar_notes_count'),
            ],
        ];
    }
}
