<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\PracticeSession;
use Illuminate\Database\Eloquent\Collection;

/**
 * The two lists the Home page shows: the session the learner can continue and
 * the finished sessions they last worked on.
 */
final readonly class HomeSessions
{
    /**
     * The most recently touched unfinished session, or null when nothing is
     * waiting.
     */
    public function continueCard(): ?PracticeSession
    {
        return PracticeSession::query()
            ->visible()
            ->unfinished()
            ->with('topic')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Finished sessions for the Home page's "Recent sessions" list, newest
     * change first. They use the same card shape as the Library so both lists
     * look and behave the same.
     *
     * @return Collection<int, PracticeSession>
     */
    public function recent(int $limit): Collection
    {
        return PracticeSession::query()
            ->visible()
            ->finished()
            ->with(['topic', 'questions.feedbackEntries'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
