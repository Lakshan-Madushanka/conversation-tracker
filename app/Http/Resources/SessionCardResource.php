<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PracticeSession;
use App\Queries\SessionSearchMatches;
use App\Support\LibraryFilters;
use App\Support\SearchText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A session as a card in the Library or on Home. When a search is in force,
 * the card also carries the matching items and the highlighted pieces of its
 * topic, so the list can explain why it matched.
 *
 * @mixin PracticeSession
 */
final class SessionCardResource extends JsonResource
{
    public function __construct(
        PracticeSession $session,
        private readonly ?LibraryFilters $filters = null,
    ) {
        parent::__construct($session);
    }

    /**
     * @return array{id: int, topic: string, session_date: string|null, last_edited_at: string|null, archived_at: string|null, status: string, counts: array{questions: int, words: int, phrases: int, mistakes: int, grammar_notes: int}, topic_segments?: list<array{text: string, match: bool}>, match_count?: int, matches?: list<array{key: string, kind: string, type: string|null, question_position: int|null, entry_id: string|null, entry_position: int|null, segments: list<array{text: string, match: bool}>}>}
     */
    public function toArray(Request $request): array
    {
        /** @var PracticeSession $session */
        $session = $this->resource;

        $session->loadMissing(['topic', 'questions.feedbackEntries']);

        $payload = [
            'id' => $session->id,
            'topic' => $session->topic->name,
            'session_date' => $session->sessionDate()?->toIso8601String(),
            'last_edited_at' => $session->lastEditedAt()?->toIso8601String(),
            'archived_at' => $session->archived_at?->toIso8601String(),
            'status' => $session->status,
            'counts' => $session->counts(),
        ];

        if (! $this->filters instanceof LibraryFilters || ! $this->filters->hasSearch()) {
            return $payload;
        }

        $terms = $this->filters->searchTerms();
        $matches = (new SessionSearchMatches($session))->matches($this->filters);

        $payload['topic_segments'] = SearchText::snippet($session->topic->name, $terms);
        $payload['match_count'] = count($matches);
        $payload['matches'] = array_slice($matches, 0, SessionSearchMatches::MAX_MATCHES);

        return $payload;
    }
}
