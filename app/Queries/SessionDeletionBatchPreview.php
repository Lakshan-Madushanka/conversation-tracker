<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\PracticeSession;
use App\Support\ProgressCalculator;
use App\Support\ProgressEntry;
use App\Support\SessionDeletionBatchEffect;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * What deleting a selection would do, worked out without changing anything.
 *
 * The same answer is used for the warning, for checking the confirmation is
 * not stale, and (after the rows are gone) to know what the result was. The
 * combined progress effect is worked out for every selected session together,
 * so the promise on the warning matches what Progress shows afterwards.
 */
final readonly class SessionDeletionBatchPreview
{
    public function __construct(
        private ProgressSessions $progressSessions,
        private ProgressCalculator $calculator,
    ) {}

    /**
     * @param  list<int>  $sessionIds
     */
    public function forIds(
        array $sessionIds,
        string $timezone,
        ?CarbonImmutable $now = null,
    ): SessionDeletionBatchEffect {
        $requestedIds = array_values(array_unique($sessionIds));
        sort($requestedIds);

        /** @var Collection<int, PracticeSession> $sessions */
        $sessions = PracticeSession::query()
            ->whereIn('id', $requestedIds)
            ->with(['topic', 'questions.feedbackEntries'])
            ->get()
            ->sortBy(fn (PracticeSession $session): int => $session->id)
            ->values();

        $now ??= CarbonImmutable::now($timezone);

        $counts = [
            'questions' => 0,
            'words' => 0,
            'phrases' => 0,
            'mistakes' => 0,
            'grammar_notes' => 0,
        ];

        $removedIds = [];

        foreach ($sessions as $session) {
            $sessionCounts = $session->counts();

            foreach ($counts as $key => $value) {
                $counts[$key] = $value + $sessionCounts[$key];
            }

            $removedIds[] = (int) $session->getKey();
        }

        $entries = $this->progressSessions->entries();

        // Flipped once so a large selection is not scanned for every entry.
        $removed = array_flip($removedIds);

        $after = array_values(array_filter(
            $entries,
            fn (ProgressEntry $entry): bool => ! isset($removed[$entry->sessionId]),
        ));

        return new SessionDeletionBatchEffect(
            requestedIds: $requestedIds,
            sessions: $sessions,
            counts: $counts,
            before: $this->calculator->calculate($entries, $timezone, $now),
            after: $this->calculator->calculate($after, $timezone, $now),
            timezone: $timezone,
        );
    }
}
