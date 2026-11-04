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
    ) {}
}
