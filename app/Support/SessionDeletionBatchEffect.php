<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PracticeSession;
use Illuminate\Support\Collection;

/**
 * Everything a bulk delete warning needs: the sessions that were asked for,
 * the ones that still exist, exactly how much they hold together, and what
 * progress would look like without them.
 *
 * The fingerprint names the whole confirmed state. The signed token made from
 * it travels back with the confirmation, so a selection that changed in
 * another tab is refused instead of being deleted under stale numbers.
 */
final readonly class SessionDeletionBatchEffect
{
    /**
     * @param  list<int>  $requestedIds
     * @param  Collection<int, PracticeSession>  $sessions
     * @param  array{questions: int, words: int, phrases: int, mistakes: int, grammar_notes: int}  $counts
     */
    public function __construct(
        public array $requestedIds,
        public Collection $sessions,
        public array $counts,
        public ProgressNumbers $before,
        public ProgressNumbers $after,
        public string $timezone,
    ) {}

    public function token(): string
    {
        return DeletionToken::sign($this->fingerprint());
    }

    public function missingCount(): int
    {
        return count($this->requestedIds) - $this->sessions->count();
    }

    public function finishedCount(): int
    {
        return $this->sessions
            ->filter(fn (PracticeSession $session): bool => $session->isFinished())
            ->count();
    }

    public function unfinishedCount(): int
    {
        return $this->sessions->count() - $this->finishedCount();
    }

    /** Finished sessions that could still be archived instead of deleted. */
    public function archivableCount(): int
    {
        return $this->sessions
            ->filter(
                fn (PracticeSession $session): bool => $session->isFinished()
                    && ! $session->isArchived(),
            )
            ->count();
    }

    /**
     * The state the warning described. Any change to one of the sessions, the
     * selection, its content or the numbers it changes makes this different.
     */
    public function fingerprint(): string
    {
        $requestedIds = $this->requestedIds;
        sort($requestedIds);

        $sessions = $this->sessions
            ->sortBy(fn (PracticeSession $session): int => $session->id)
            ->values()
            ->map(fn (PracticeSession $session): array => [
                'id' => $session->id,
                'topic' => $session->topic->name,
                'session_date' => $session->created_at?->toIso8601String(),
                'status' => $session->status,
                'version' => $session->version,
                'finished_at' => $session->finished_at?->toIso8601String(),
                'archived_at' => $session->archived_at?->toIso8601String(),
                'last_changed_at' => $session->last_changed_at?->toIso8601String(),
            ])
            ->all();

        return json_encode([
            'requested_ids' => $requestedIds,
            'sessions' => $sessions,
            'counts' => $this->counts,
            'progress' => [
                'before' => $this->before->toArray(),
                'after' => $this->after->toArray(),
            ],
            'timezone' => $this->timezone,
        ], JSON_THROW_ON_ERROR);
    }
}
