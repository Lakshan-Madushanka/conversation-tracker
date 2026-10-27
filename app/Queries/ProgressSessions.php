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
 * session, and only the columns the counts read are selected, so a large
 * Library is not read column by column for nothing.
 */
final readonly class ProgressSessions
{
    /**
     * The entry columns a session's counts read: its type, and every column
     * that decides whether the row holds anything at all.
     */
    private const string ENTRY_COLUMNS = 'id,practice_question_id,type,content_html,content_text,correction_html,correction_text,reason,example,example_html';

    /**
     * @param  list<int>  $exceptIds
     * @return list<ProgressEntry>
     */
    public function entries(array $exceptIds = []): array
    {
        /** @var Collection<int, PracticeSession> $sessions */
        $sessions = PracticeSession::query()
            ->countable()
            ->select(['id', 'finished_at'])
            ->with([
                'questions:id,practice_session_id,question_text,answer_text',
                'questions.feedbackEntries:'.self::ENTRY_COLUMNS,
            ])
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
