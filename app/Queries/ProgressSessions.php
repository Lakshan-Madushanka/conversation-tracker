<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\PracticeSession;
use App\Support\ProgressEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The finished sessions progress is worked out from, including archived ones.
 * Their content is loaded in bulk, so a large Library never costs a query per
 * session.
 */
final readonly class ProgressSessions
{
    /**
     * @param  list<int>  $exceptIds
     * @return list<ProgressEntry>
     */
    public function entries(array $exceptIds = []): array
    {
        /** @var Collection<int, PracticeSession> $sessions */
        $sessions = PracticeSession::query()
            ->countable()
            ->with(['questions.feedbackEntries'])
            ->when(
                $exceptIds !== [],
                fn (Builder $query): Builder => $query->whereNotIn('id', $exceptIds),
            )
            ->get();

        $entries = [];

        foreach ($sessions as $session) {
            $counts = $session->counts();

            $entries[] = new ProgressEntry(
                sessionId: $session->id,
                finishedAt: $session->finished_at,
                words: $counts['words'],
                phrases: $counts['phrases'],
                mistakes: $counts['mistakes'],
                grammarNotes: $counts['grammar_notes'],
            );
        }

        return $entries;
    }
}
