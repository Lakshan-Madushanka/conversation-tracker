<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeSession;
use App\Support\EntrySearch;
use App\Support\LibraryFilters;
use App\Support\SearchText;

/**
 * The items inside one session that hold every search word, in the order the
 * reading page shows them: the topic, then each question, then its boxes in
 * their usual order.
 */
final readonly class SessionSearchMatches
{
    /** How many matching items one session shows before "show more". */
    public const int MAX_MATCHES = 50;

    public function __construct(private PracticeSession $session) {}

    /**
     * @return list<array{key: string, kind: string, type: string|null, question_position: int|null, entry_id: string|null, entry_position: int|null, segments: list<array{text: string, match: bool}>}>
     */
    public function matches(LibraryFilters $filters): array
    {
        $terms = $filters->searchTerms();

        if ($terms === []) {
            return [];
        }

        $types = $filters->types();
        $this->session->loadMissing(['topic', 'questions.feedbackEntries']);

        $matches = [];

        if ($types === [] && SearchText::matches($this->session->topic->name, $terms)) {
            $matches[] = [
                'key' => 'topic',
                'kind' => 'topic',
                'type' => null,
                'question_position' => null,
                'entry_id' => null,
                'entry_position' => null,
                'segments' => SearchText::snippet($this->session->topic->name, $terms),
            ];
        }

        foreach ($this->session->questions as $question) {
            if ($types === []) {
                if (SearchText::matches($question->question_text, $terms)) {
                    $matches[] = [
                        'key' => 'question:'.$question->position,
                        'kind' => 'question',
                        'type' => null,
                        'question_position' => $question->position,
                        'entry_id' => null,
                        'entry_position' => null,
                        'segments' => SearchText::snippet($question->question_text, $terms),
                    ];
                }

                if (SearchText::matches($question->answer_text, $terms)) {
                    $matches[] = [
                        'key' => 'answer:'.$question->position,
                        'kind' => 'answer',
                        'type' => null,
                        'question_position' => $question->position,
                        'entry_id' => null,
                        'entry_position' => null,
                        'segments' => SearchText::snippet($question->answer_text, $terms),
                    ];
                }
            }

            $entries = $question->feedbackEntries
                ->filter(fn (PracticeFeedbackEntry $entry): bool => ! $entry->isBlank())
                ->sortBy(fn (PracticeFeedbackEntry $entry): array => [
                    $entry->type->sortOrder(),
                    $entry->position,
                    $entry->id,
                ])
                ->values();

            foreach ($entries as $entry) {
                if ($types !== [] && ! in_array($entry->type->value, $types, true)) {
                    continue;
                }

                $searchText = $entry->search_text ?? EntrySearch::text($entry);

                if (! SearchText::matches($searchText, $terms)) {
                    continue;
                }

                $field = EntrySearch::bestField($entry, $terms);

                $matches[] = [
                    'key' => 'entry:'.$entry->type->value.':'.($entry->client_id ?? $entry->id),
                    'kind' => 'entry',
                    'type' => $entry->type->value,
                    'question_position' => $question->position,
                    'entry_id' => $entry->client_id,
                    'entry_position' => $entry->position,
                    'segments' => SearchText::snippet($field['text'], $terms),
                ];
            }
        }

        return $matches;
    }
}
