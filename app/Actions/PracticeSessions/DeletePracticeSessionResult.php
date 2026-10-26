<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

/**
 * The answer after a permanent delete. A session that was already gone is a
 * success too: repeating the same confirmation must never raise an error.
 */
final readonly class DeletePracticeSessionResult
{
    /**
     * @param  array{questions: int, words: int, phrases: int, mistakes: int, grammar_notes: int}  $counts
     */
    public function __construct(
        public int $sessionId,
        public string $topic,
        public array $counts,
        public bool $alreadyDeleted,
    ) {}
}
