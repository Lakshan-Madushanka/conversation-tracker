<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

use App\Models\PracticeSession;

/**
 * Discards a session the learner never filled in.
 *
 * Discard is only ever offered for empty sessions, so this refuses to remove
 * one that holds any content. Deleting sessions with content belongs to the
 * Library feature later, not here.
 */
final readonly class DiscardPracticeSession
{
    /** Whether the session can be discarded (it holds nothing). */
    public function allows(PracticeSession $session): bool
    {
        return $session->isEmpty();
    }

    public function handle(PracticeSession $session): void
    {
        $session->delete();
    }
}
