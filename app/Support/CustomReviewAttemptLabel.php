<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CustomReviewAttemptStatus;
use App\Models\CustomReviewAttempt;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * How an attempt is worded on a card and in a history, in one place.
 *
 * The listing card and the review's own page read these words, so the latest
 * number, the previous status and the "3 attempts · last on 12 Oct" line can
 * never disagree. A Not started attempt keeps no number and no date of its own,
 * so it never becomes "the latest".
 */
final class CustomReviewAttemptLabel
{
    /** What the card says when the review has never been attempted. */
    public const string NONE = 'No attempts yet';

    /** What the card says while an attempt is running. */
    public const string IN_PROGRESS = 'Attempt in progress';

    /** The calm line the finish summary ends on. */
    public const string ENCOURAGEMENT = 'Every attempt is practice, never a test. Nicely done.';

    /** The latest attempt's number, or the "never attempted" line. */
    public static function number(?CustomReviewAttempt $latest): string
    {
        if (! $latest instanceof CustomReviewAttempt || $latest->number === null) {
            return self::NONE;
        }

        return 'Attempt '.$latest->number;
    }

    /** The attempt before the latest, named by its status. */
    public static function previous(?CustomReviewAttemptStatus $status): ?string
    {
        return $status instanceof CustomReviewAttemptStatus
            ? 'Previous: '.$status->label()
            : null;
    }

    /**
     * The history line: how many attempts were started and when the last one
     * was, or that one is running now.
     */
    public static function summary(
        int $count,
        ?CustomReviewAttempt $latest,
        ?Request $request = null,
    ): string {
        if ($count === 0 || ! $latest instanceof CustomReviewAttempt) {
            return self::NONE;
        }

        if ($latest->isInProgress()) {
            return self::IN_PROGRESS;
        }

        $startedAt = $latest->started_at;

        if (! $startedAt instanceof CarbonImmutable) {
            return $count === 1 ? '1 attempt' : $count.' attempts';
        }

        $zone = app(LearnerDay::class)->timezone($request);
        $local = $startedAt->setTimezone($zone);
        $sameYear = $local->year === CarbonImmutable::now($zone)->year;
        $date = $local->format($sameYear ? 'j M' : 'j M Y');

        return ($count === 1 ? '1 attempt' : $count.' attempts').' · last on '.$date;
    }

    /**
     * The quiet heading above an attempt's progress:
     * "[Review name] · [n]th attempt".
     */
    public static function heading(string $reviewName, CustomReviewAttempt $attempt): string
    {
        if ($attempt->number === null) {
            return $reviewName.' · Not started';
        }

        return $reviewName.' · '.Ordinal::of($attempt->number).' attempt';
    }
}
