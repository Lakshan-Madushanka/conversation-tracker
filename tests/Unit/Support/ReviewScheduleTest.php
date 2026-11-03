<?php

declare(strict_types=1);

use App\Enums\ReviewRating;
use App\Support\ReviewSchedule;
use Carbon\CarbonImmutable;

/**
 * One rating, given on the named day, in the order the learner gave it.
 *
 * @return array{rating: ReviewRating, day: CarbonImmutable}
 */
function historyEntry(ReviewRating $rating, string $day): array
{
    return ['rating' => $rating, 'day' => CarbonImmutable::parse($day, 'Europe/Berlin')->startOfDay()];
}

describe('the schedule rule', function (): void {
    it('marks a never-reviewed item as having no schedule', function (): void {
        expect(ReviewSchedule::rebuild([]))->toBeNull();
    });

    it('brings a Hard item back the next day', function (): void {
        $state = ReviewSchedule::rebuild([historyEntry(ReviewRating::Hard, '2026-11-03')]);

        expect($state)->not->toBeNull()
            ->and($state->dueOn->toDateString())->toBe('2026-11-04')
            ->and($state->ratedCount)->toBe(1)
            ->and($state->easyRun)->toBe(0)
            ->and($state->lastRating)->toBe(ReviewRating::Hard);
    });

    it('brings an Okay item back after about three days', function (): void {
        $state = ReviewSchedule::rebuild([historyEntry(ReviewRating::Okay, '2026-11-03')]);

        expect($state->dueOn->toDateString())->toBe('2026-11-06')
            ->and($state->easyRun)->toBe(0);
    });

    it('brings an Easy item back after about a week', function (): void {
        $state = ReviewSchedule::rebuild([historyEntry(ReviewRating::Easy, '2026-11-03')]);

        expect($state->dueOn->toDateString())->toBe('2026-11-10')
            ->and($state->easyRun)->toBe(1);
    });

    it('grows the gap with consecutive Easy ratings, up to about two months', function (): void {
        $history = [historyEntry(ReviewRating::Easy, '2026-11-03')];

        expect(ReviewSchedule::rebuild($history)->dueOn->toDateString())->toBe('2026-11-10');

        $history[] = historyEntry(ReviewRating::Easy, '2026-11-10');
        expect(ReviewSchedule::rebuild($history)->dueOn->toDateString())->toBe('2026-11-24');

        $history[] = historyEntry(ReviewRating::Easy, '2026-11-24');
        expect(ReviewSchedule::rebuild($history)->dueOn->toDateString())->toBe('2026-12-24');

        $history[] = historyEntry(ReviewRating::Easy, '2026-12-24');
        expect(ReviewSchedule::rebuild($history)->dueOn->toDateString())->toBe('2027-02-22');

        // The cap: repeated Easy ratings never stretch the gap beyond two months.
        $history[] = historyEntry(ReviewRating::Easy, '2027-02-22');
        expect(ReviewSchedule::rebuild($history)->dueOn->toDateString())->toBe('2027-04-23');

        $history[] = historyEntry(ReviewRating::Easy, '2027-04-23');
        expect(ReviewSchedule::rebuild($history)->dueOn->toDateString())->toBe('2027-06-22');
    });

    it('resets the run of consecutive Easy ratings on Hard', function (): void {
        $state = ReviewSchedule::rebuild([
            historyEntry(ReviewRating::Easy, '2026-11-01'),
            historyEntry(ReviewRating::Easy, '2026-11-08'),
            historyEntry(ReviewRating::Hard, '2026-11-15'),
        ]);

        expect($state->easyRun)->toBe(0)
            ->and($state->dueOn->toDateString())->toBe('2026-11-16');

        // After the Hard rating, one fresh Easy goes back to the first gap.
        $state = ReviewSchedule::rebuild([
            historyEntry(ReviewRating::Easy, '2026-11-01'),
            historyEntry(ReviewRating::Easy, '2026-11-08'),
            historyEntry(ReviewRating::Hard, '2026-11-15'),
            historyEntry(ReviewRating::Easy, '2026-11-16'),
        ]);

        expect($state->easyRun)->toBe(1)
            ->and($state->dueOn->toDateString())->toBe('2026-11-23');
    });

    it('leaves the run of Easy ratings alone on Okay', function (): void {
        $state = ReviewSchedule::rebuild([
            historyEntry(ReviewRating::Easy, '2026-11-01'),
            historyEntry(ReviewRating::Okay, '2026-11-08'),
        ]);

        expect($state->easyRun)->toBe(1)
            ->and($state->dueOn->toDateString())->toBe('2026-11-11');

        $state = ReviewSchedule::rebuild([
            historyEntry(ReviewRating::Easy, '2026-11-01'),
            historyEntry(ReviewRating::Okay, '2026-11-08'),
            historyEntry(ReviewRating::Easy, '2026-11-11'),
        ]);

        expect($state->easyRun)->toBe(2)
            ->and($state->dueOn->toDateString())->toBe('2026-11-25');
    });

    it('never lets an item disappear from review forever', function (): void {
        $history = [];
        $day = CarbonImmutable::parse('2026-01-01', 'Europe/Berlin');

        // A long run of Easy ratings still ends with the item coming back.
        for ($i = 0; $i < 12; $i++) {
            $history[] = ['rating' => ReviewRating::Easy, 'day' => $day];
            $state = ReviewSchedule::rebuild($history);
            $day = $state->dueOn;
        }

        expect($state->easyRun)->toBe(12)
            ->and($state->dueOn->toDateString())->toBe($state->lastRatedOn->addDays(60)->toDateString());
    });

    it('applies two ratings of the same day in the order they were given', function (): void {
        $easyThenHard = ReviewSchedule::rebuild([
            historyEntry(ReviewRating::Easy, '2026-11-03'),
            historyEntry(ReviewRating::Hard, '2026-11-03'),
        ]);

        expect($easyThenHard->easyRun)->toBe(0)
            ->and($easyThenHard->dueOn->toDateString())->toBe('2026-11-04');

        $hardThenEasy = ReviewSchedule::rebuild([
            historyEntry(ReviewRating::Hard, '2026-11-03'),
            historyEntry(ReviewRating::Easy, '2026-11-03'),
        ]);

        expect($hardThenEasy->easyRun)->toBe(1)
            ->and($hardThenEasy->dueOn->toDateString())->toBe('2026-11-10');
    });

    it('is deterministic for the same history', function (): void {
        $history = [
            historyEntry(ReviewRating::Hard, '2026-09-01'),
            historyEntry(ReviewRating::Okay, '2026-09-02'),
            historyEntry(ReviewRating::Easy, '2026-09-05'),
            historyEntry(ReviewRating::Easy, '2026-09-12'),
        ];

        $first = ReviewSchedule::rebuild($history);
        $second = ReviewSchedule::rebuild($history);

        expect($first->dueOn->toDateString())->toBe($second->dueOn->toDateString())
            ->and($first->easyRun)->toBe($second->easyRun)
            ->and($first->ratedCount)->toBe(4);
    });

    it('carries the daylight saving change without shifting the day', function (): void {
        // The spring forward misses the wall clock jump; a due day is always
        // midnight in the learner's own zone, so adding days never drifts.
        $state = ReviewSchedule::rebuild([historyEntry(ReviewRating::Easy, '2026-03-27')]);
        expect($state->dueOn->toDateString())->toBe('2026-04-03');

        $state = ReviewSchedule::rebuild([historyEntry(ReviewRating::Easy, '2026-10-23')]);
        expect($state->dueOn->toDateString())->toBe('2026-10-30');

        $state = ReviewSchedule::rebuild([historyEntry(ReviewRating::Okay, '2026-03-29')]);
        expect($state->dueOn->toDateString())->toBe('2026-04-01');
    });

    it('keeps the intervals in one named place', function (): void {
        expect(ReviewSchedule::HARD_DUE_IN_DAYS)->toBe(1)
            ->and(ReviewSchedule::OKAY_DUE_IN_DAYS)->toBe(3)
            ->and(ReviewSchedule::EASY_GAPS)->toBe([7, 14, 30, 60]);
    });
});
