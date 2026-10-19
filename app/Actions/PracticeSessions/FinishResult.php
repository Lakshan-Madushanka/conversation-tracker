<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

use App\Models\PracticeSession;

/**
 * The outcome of finishing a session: the (possibly unchanged) session, plus
 * how many empty question cards were removed. When `empty` is true the session
 * was left untouched, because it held nothing worth finishing.
 */
final readonly class FinishResult
{
    public function __construct(
        public PracticeSession $session,
        public bool $empty,
        public int $emptyQuestionsRemoved,
    ) {}
}
