<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Finishes a session in one safe, repeatable action.
 *
 * It removes the question cards that hold nothing, marks the session finished,
 * and records the moment it happened — all together in a transaction, so a
 * session is never left half-finished. Finishing an already-finished session
 * changes nothing, so pressing Finish twice (or retrying after a failure) is
 * always safe and never duplicates work.
 *
 * The version is bumped only when something actually changed, so re-finishing
 * a finished session does not force a needless conflict in another tab.
 */
final readonly class FinishPracticeSession
{
    public function handle(PracticeSession $session): FinishResult
    {
        return DB::transaction(function () use ($session): FinishResult {
            /** @var PracticeSession $locked */
            $locked = PracticeSession::query()
                ->whereKey($session->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->load('questions.feedbackEntries');

            if ($locked->isEmpty()) {
                return new FinishResult(session: $locked, empty: true, emptyQuestionsRemoved: 0);
            }

            $removed = $this->removeEmptyQuestions($locked);

            $wasFinished = $locked->isFinished() && $locked->finished_at !== null;

            $locked->status = PracticeSession::STATUS_FINISHED;
            $locked->finished_at ??= Carbon::now();

            if ($removed > 0 || ! $wasFinished) {
                $locked->version += 1;
            }

            $locked->save();

            return new FinishResult(
                session: $locked->load('questions.feedbackEntries'),
                empty: false,
                emptyQuestionsRemoved: $removed,
            );
        });
    }

    /**
     * Removes the question cards that hold nothing, then keeps the remaining
     * positions in order. A card with anything at all in it is kept.
     */
    private function removeEmptyQuestions(PracticeSession $session): int
    {
        $empty = $session->questions
            ->filter(fn (PracticeQuestion $question): bool => ! $question->hasContent());

        foreach ($empty as $question) {
            $question->feedbackEntries()->delete();
            $question->delete();
        }

        if ($empty->isEmpty()) {
            return 0;
        }

        $session->questions()
            ->orderBy('position')
            ->get()
            ->each(function (PracticeQuestion $question, int $index): void {
                $position = $index + 1;

                if ($question->position !== $position) {
                    $question->position = $position;
                    $question->save();
                }
            });

        return $empty->count();
    }
}
