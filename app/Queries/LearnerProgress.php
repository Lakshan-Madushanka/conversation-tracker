<?php

declare(strict_types=1);

namespace App\Queries;

use App\Support\LearnerDay;
use App\Support\ProgressCalculator;
use App\Support\ProgressNumbers;
use Illuminate\Http\Request;

/**
 * The learner's current progress, worked out from the finished sessions that
 * still exist. Home and Progress both read it here, so they cannot disagree.
 */
final readonly class LearnerProgress
{
    public function __construct(
        private ProgressSessions $sessions,
        private ProgressCalculator $calculator,
        private LearnerDay $day,
    ) {}

    public function numbers(?Request $request = null): ProgressNumbers
    {
        $request ??= request();

        return $this->calculator->calculate(
            $this->sessions->entries(),
            $this->day->timezone($request),
            $this->day->now($request),
        );
    }
}
