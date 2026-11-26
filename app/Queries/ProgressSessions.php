<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Support\ProgressEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The sessions progress is worked out from. Content comes from finished
 * sessions, including archived ones, and their content is loaded in bulk so a
 * large Library never costs a query per session. Practice days come from the
 * moments any session, finished or not, recorded a complete conversation.
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
            ->select(['id'])
            ->with([
                'questions:id,practice_session_id,question_text,answer_text,feedback_text',
                'questions.feedbackEntries:'.self::ENTRY_COLUMNS,
            ])
            ->when(
                $exceptIds !== [],
                fn (Builder $query): Builder => $query->whereNotIn('id', $exceptIds),
            )
            ->get();

        $momentsBySession = $this->practiceMomentsBySession($exceptIds);

        $entries = [];
        $finishedIds = [];

        foreach ($sessions as $session) {
            $counts = $session->counts();
            $finishedIds[$session->id] = true;

            $entries[] = new ProgressEntry(
                sessionId: $session->id,
                finished: true,
                words: $counts['words'],
                phrases: $counts['phrases'],
                mistakes: $counts['mistakes'],
                grammarNotes: $counts['grammar_notes'],
                practiceMoments: $momentsBySession[$session->id] ?? [],
            );
        }

        foreach ($momentsBySession as $sessionId => $moments) {
            if (isset($finishedIds[$sessionId])) {
                continue;
            }

            $entries[] = new ProgressEntry(
                sessionId: $sessionId,
                finished: false,
                words: 0,
                phrases: 0,
                mistakes: 0,
                grammarNotes: 0,
                practiceMoments: $moments,
            );
        }

        return $entries;
    }

    /**
     * When each session recorded a complete conversation, keyed by session.
     * Read from the moment the question was completed, so editing it later
     * never moves its practice day.
     *
     * @param  list<int>  $exceptIds
     * @return array<int, list<CarbonImmutable>>
     */
    private function practiceMomentsBySession(array $exceptIds): array
    {
        /** @var Collection<int, PracticeQuestion> $questions */
        $questions = PracticeQuestion::query()
            ->whereNotNull('completed_at')
            ->when(
                $exceptIds !== [],
                fn (Builder $query): Builder => $query->whereNotIn('practice_session_id', $exceptIds),
            )
            ->select(['practice_session_id', 'completed_at'])
            ->get();

        $moments = [];

        foreach ($questions as $question) {
            $moment = $question->completed_at;

            if ($moment === null) {
                continue;
            }

            $moments[$question->practice_session_id][] = $moment;
        }

        return $moments;
    }
}
