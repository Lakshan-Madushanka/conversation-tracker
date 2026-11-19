<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Notebook;
use App\Models\PracticeFeedbackEntry;
use App\Support\CollectionMatch;
use App\Support\SearchText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One notebook on the listing: its name, topic, plain-text description,
 * last-updated time and the counts the listing query aggregated. A search that
 * matched through a saved row adds a short hint naming that row, so a card
 * never says it matched without showing where.
 */
final class NotebookCardResource extends JsonResource
{
    private ?PracticeFeedbackEntry $matchEntry = null;

    /** @var list<string> */
    private array $matchTerms = [];

    /**
     * The row a search found, when the card's own words do not already explain
     * the match. Passed separately so the page reads every row in one query.
     *
     * @param  list<string>  $terms
     */
    public function withEntryMatch(?PracticeFeedbackEntry $entry, array $terms): self
    {
        $this->matchEntry = $entry;
        $this->matchTerms = $terms;

        return $this;
    }

    /**
     * @return array{id: int, name: string, topic: string, description: string, last_updated_at: string|null, counts: array{words: int, phrases: int, grammar_notes: int}, match: array{label: string, segments: list<array{text: string, match: bool}>, type: string, entry: string|null, position: int}|null}
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
            'match' => $this->match(),
        ];
    }

    /**
     * The row hint when name, topic and description do not hold every term.
     * The pieces are plain text cut from the saved row, never saved markup.
     *
     * @return array{label: string, segments: list<array{text: string, match: bool}>, type: string, entry: string|null, position: int}|null
     */
    private function match(): ?array
    {
        if (! $this->matchEntry instanceof PracticeFeedbackEntry || $this->matchTerms === []) {
            return null;
        }

        $notebook = $this->resource;

        foreach ([
            (string) $notebook->name,
            (string) $notebook->topic->name,
            (string) $notebook->description_text,
        ] as $visible) {
            if (SearchText::matches($visible, $this->matchTerms)) {
                return null;
            }
        }

        $hint = CollectionMatch::forEntry($this->matchEntry, $this->matchTerms);

        if ($hint === null) {
            return null;
        }

        return [
            'label' => $hint['label'],
            'segments' => $hint['segments'],
            'type' => $this->matchEntry->type->value,
            'entry' => $this->matchEntry->client_id,
            'position' => $this->matchEntry->position,
        ];
    }
}
