<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Works out progress totals, practice days and the current streak from a list
 * of finished sessions.
 *
 * It takes the list it is given, so the same rules answer "what does progress
 * show now?" and "what would it show after deleting these sessions?" without
 * changing anything.
 */
final readonly class ProgressCalculator
{
    /**
     * @param  list<ProgressEntry>  $entries
     */
    public function calculate(
        array $entries,
        string $timezone,
        ?CarbonImmutable $now = null,
    ): ProgressNumbers {
        $now ??= CarbonImmutable::now($timezone);

        $words = 0;
        $phrases = 0;
        $mistakes = 0;
        $grammarNotes = 0;

        /** @var array<string, true> $practiceDays */
        $practiceDays = [];

        foreach ($entries as $entry) {
            $words += $entry->words;
            $phrases += $entry->phrases;
            $mistakes += $entry->mistakes;
            $grammarNotes += $entry->grammarNotes;

            if ($entry->finishedAt === null) {
                continue;
            }

            // Practice happens on the learner's calendar day, not the server's.
            $day = $entry->finishedAt->setTimezone($timezone)->format('Y-m-d');
            $practiceDays[$day] = true;
        }

        return new ProgressNumbers(
            words: $words,
            phrases: $phrases,
            mistakes: $mistakes,
            grammarNotes: $grammarNotes,
            practiceDays: count($practiceDays),
            currentStreak: $this->currentStreak($practiceDays, $now, $timezone),
        );
    }

    /**
     * The run of consecutive days ending today, or ending yesterday when the
     * learner has not practised today yet. A run that ended earlier is over.
     *
     * @param  array<string, true>  $practiceDays
     */
    private function currentStreak(
        array $practiceDays,
        CarbonImmutable $now,
        string $timezone,
    ): int {
        if ($practiceDays === []) {
            return 0;
        }

        $anchor = $now->setTimezone($timezone)->format('Y-m-d');

        if (! isset($practiceDays[$anchor])) {
            $anchor = $this->previousDay($anchor);

            if (! isset($practiceDays[$anchor])) {
                return 0;
            }
        }

        $streak = 0;

        while (isset($practiceDays[$anchor])) {
            $streak += 1;
            $anchor = $this->previousDay($anchor);
        }

        return $streak;
    }

    /**
     * The calendar day before a `Y-m-d` key. Calendar arithmetic runs in UTC so
     * a daylight-saving change can never skip or repeat a day.
     */
    private function previousDay(string $day): string
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $day, 'UTC')
            ->subDay()
            ->format('Y-m-d');
    }
}
