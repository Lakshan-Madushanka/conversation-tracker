<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How one attempt at a custom review stands.
 *
 * These describe practice, never a score, and are a different thing from the
 * review's own Empty, Draft and Finished status. Neither ever sets the other.
 *
 * Not started exists for an attempt that was created but never begun; it keeps
 * no number and the system removes it when it stays empty. In progress is an
 * attempt the learner is working through or has left for later. Completed means
 * every item is rated or skipped; Finished early means the learner stopped it
 * themselves.
 */
enum CustomReviewAttemptStatus: string
{
    case NotStarted = 'not_started';

    case InProgress = 'in_progress';

    case Completed = 'completed';

    case FinishedEarly = 'finished_early';

    /** The words the learner sees. */
    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not started',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::FinishedEarly => 'Finished early',
        };
    }

    /** Whether the attempt has ended, whichever way it ended. */
    public function isFinished(): bool
    {
        return $this === self::Completed || $this === self::FinishedEarly;
    }

    /** Whether the attempt has a number and belongs in the count. */
    public function isCounted(): bool
    {
        return $this !== self::NotStarted;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}
