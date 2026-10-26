<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PracticeSession;

/**
 * Everything the delete warning needs: which session is going, exactly how
 * much it holds, and what progress would look like without it.
 *
 * The fingerprint names the state the learner saw. The signed token made from
 * it is sent back with the confirmation, so a session that changed in another
 * tab is refused instead of being deleted under stale numbers.
 */
final readonly class SessionDeletionEffect
{
    /**
     * @param  array{questions: int, words: int, phrases: int, mistakes: int, grammar_notes: int}  $counts
     */
    public function __construct(
        public PracticeSession $session,
        public array $counts,
        public ProgressNumbers $before,
        public ProgressNumbers $after,
        public string $timezone,
    ) {}

    public function token(): string
    {
        return DeletionToken::sign($this->fingerprint());
    }

    /**
     * The state the warning described. Any change to the session, its content
     * or the numbers it changes makes this fingerprint different.
     */
    public function fingerprint(): string
    {
        return json_encode([
            'session' => [
                'id' => $this->session->id,
                'topic' => $this->session->topic->name,
                'session_date' => $this->session->created_at?->toIso8601String(),
                'status' => $this->session->status,
                'version' => $this->session->version,
                'finished_at' => $this->session->finished_at?->toIso8601String(),
                'archived_at' => $this->session->archived_at?->toIso8601String(),
                'last_changed_at' => $this->session->last_changed_at?->toIso8601String(),
            ],
            'counts' => $this->counts,
            'progress' => [
                'before' => $this->before->toArray(),
                'after' => $this->after->toArray(),
            ],
            'timezone' => $this->timezone,
        ], JSON_THROW_ON_ERROR);
    }
}
