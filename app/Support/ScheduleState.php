<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ReviewRating;
use Carbon\CarbonImmutable;

/**
 * The one stored state of an item's schedule: when it is next worth
 * revisiting and the few counters the rule grows from. Pure value; the
 * rules that produce it live in `ReviewSchedule`.
 */
final readonly class ScheduleState
{
    public function __construct(
        /** The local day the item is next worth revisiting. */
        public CarbonImmutable $dueOn,
        /** The local day the last rating was given. */
        public CarbonImmutable $lastRatedOn,
        public ReviewRating $lastRating,
        public int $ratedCount,
        /** How many Easy ratings in a row end the history. */
        public int $easyRun,
        /**
         * How many ratings in a row the last one has been — an Okay breaks a
         * run of Easy ratings here, even though the gap above grows through it.
         * The finish screen's status suggestions read this.
         */
        public int $streakCount,
    ) {}
}
