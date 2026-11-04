<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ReviewRating;
use Carbon\CarbonImmutable;

/**
 * The schedule rule, and the only place it lives.
 *
 * Given the full rating history of one item, in the order the ratings were
 * given, it works out the day the item is next worth revisiting:
 *
 * - a Hard rating brings the item back the next day and resets the run of
 *   consecutive Easy ratings;
 * - an Okay rating brings the item back after a few days and leaves the run
 *   of Easy ratings alone;
 * - an Easy rating brings the item back after a gap that grows with the run
 *   (a week, then two weeks, then a month, then two months as the cap);
 * - every rating counts, and nothing disappears from review forever.
 *
 * The history is rebuilt from the ratings every time anything changes — a
 * new rating, an undo, a typo fix that moves ratings to a new identity — so
 * the stored schedule can never disagree with the history: this function
 * decides, the stored row only remembers.
 */
final class ReviewSchedule
{
    /** Days after a Hard rating until the item is due again. */
    public const int HARD_DUE_IN_DAYS = 1;

    /** Days after an Okay rating until the item is due again. */
    public const int OKAY_DUE_IN_DAYS = 3;

    /**
     * The gaps after consecutive Easy ratings: a week, two weeks, a month,
     * then two months as the cap. The run of Easy ratings picks the gap;
     * beyond the list the last one is used, so nothing disappears forever.
     */
    public const array EASY_GAPS = [7, 14, 30, 60];

    /**
     * The schedule for one item from its rating history, given in the order
     * the ratings were given. Null when the item has never been rated — a
     * never-reviewed item is "new" and due right away, but no schedule row
     * exists for it.
     *
     * @param  iterable<array{rating: ReviewRating, day: CarbonImmutable}>  $history
     */
    public static function rebuild(iterable $history): ?ScheduleState
    {
        $ratedCount = 0;
        $easyRun = 0;
        $last = null;

        foreach ($history as $entry) {
            $ratedCount += 1;

            if ($entry['rating'] === ReviewRating::Easy) {
                $easyRun += 1;
            } elseif ($entry['rating'] === ReviewRating::Hard) {
                $easyRun = 0;
            }

            $last = $entry;
        }

        if ($last === null) {
            return null;
        }

        $lastRating = $last['rating'];
        $lastDay = $last['day'];

        $gap = match ($lastRating) {
            ReviewRating::Hard => self::HARD_DUE_IN_DAYS,
            ReviewRating::Okay => self::OKAY_DUE_IN_DAYS,
            ReviewRating::Easy => self::EASY_GAPS[min($easyRun, count(self::EASY_GAPS)) - 1],
        };

        return new ScheduleState(
            dueOn: $lastDay->addDays($gap)->startOfDay(),
            lastRatedOn: $lastDay->startOfDay(),
            lastRating: $lastRating,
            ratedCount: $ratedCount,
            easyRun: $easyRun,
        );
    }
}
