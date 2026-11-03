<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;

/**
 * The learner's current moment and their own local day, in one place.
 *
 * Everything that needs "today" — the schedule, the daily suggestion, the
 * "You've reviewed today" state — reads it from here, so the tests can pin
 * the moment (or use Carbon's test now) and walk through midnight and
 * daylight saving changes without waiting.
 *
 * The time zone comes from the learner's browser (the `tz` address parameter
 * or the `app_timezone` cookie the library uses, normalised the same way),
 * and falls back to the application's zone. An unknown value never fails a
 * page.
 */
final class LearnerDay
{
    /** Pinned moment for tests, already carrying the learner's zone. */
    private ?CarbonImmutable $pretendNow = null;

    /**
     * Pin "now" for the rest of the request (or test). Pass null to unpin.
     */
    public function pretend(?CarbonImmutable $now): void
    {
        $this->pretendNow = $now;
    }

    /** The learner's time zone, normalised once. */
    public function timezone(?Request $request = null): string
    {
        if ($this->pretendNow instanceof CarbonImmutable) {
            return $this->pretendNow->timezoneName;
        }

        $request ??= request();

        $tz = $request->query('tz');

        if (! is_string($tz) || $tz === '') {
            $tz = $request->cookie('app_timezone');
        }

        return LearnerTimezone::normalise($tz);
    }

    /** The learner's current moment, in their own zone. */
    public function now(?Request $request = null): CarbonImmutable
    {
        $request ??= request();

        if ($this->pretendNow instanceof CarbonImmutable) {
            return $this->pretendNow->setTimezone($this->timezone($request));
        }

        return CarbonImmutable::now($this->timezone($request));
    }

    /** The learner's local calendar day. */
    public function today(?Request $request = null): CarbonImmutable
    {
        return $this->now($request)->startOfDay();
    }

    /** The local calendar day a moment falls on, in the learner's zone. */
    public function dayOf(DateTimeInterface $moment, ?Request $request = null): CarbonImmutable
    {
        return CarbonImmutable::instance($moment)
            ->setTimezone($this->timezone($request))
            ->startOfDay();
    }
}
