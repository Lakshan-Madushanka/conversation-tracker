<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\PracticeSession;
use App\Support\ProgressCalculator;
use App\Support\ProgressEntry;
use App\Support\SessionDeletionEffect;
use Carbon\CarbonImmutable;

/**
 * What deleting one session would do, worked out without changing anything.
 *
 * The same answer is used for the warning, for checking a confirmation is not
 * stale, and (after the rows are gone) to know what progress should now show.
 */
final readonly class SessionDeletionPreview
{
    public function __construct(
        private ProgressSessions $progressSessions,
        private ProgressCalculator $calculator,
    ) {}

    public function for(
        PracticeSession $session,
        string $timezone,
        ?CarbonImmutable $now = null,
    ): SessionDeletionEffect {
        $session->loadMissing(['topic', 'questions.feedbackEntries']);

        $now ??= CarbonImmutable::now($timezone);

        $entries = $this->progressSessions->entries();
        $after = array_values(array_filter(
            $entries,
            fn (ProgressEntry $entry): bool => $entry->sessionId !== $session->id,
        ));

        return new SessionDeletionEffect(
            session: $session,
            counts: $session->counts(),
            before: $this->calculator->calculate($entries, $timezone, $now),
            after: $this->calculator->calculate($after, $timezone, $now),
            timezone: $timezone,
        );
    }
}
