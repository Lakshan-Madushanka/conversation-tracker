<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Works out progress totals, practice days and the current streak from the
 * sessions it is given.
 *
 * Totals come from finished sessions. A practice day is the learner-local day
 * of any complete conversation, even in a session that is not finished yet, so
 * a topic carried across several days still counts each day it was practised.
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

        $sessions = 0;

        foreach ($entries as $entry) {
            if ($entry->finished) {
                $words += $entry->words;
                $phrases += $entry->phrases;
                $mistakes += $entry->mistakes;
                $grammarNotes += $entry->grammarNotes;
                $sessions += 1;
            }

            foreach ($entry->practiceMoments as $moment) {
                // Practice happens on the learner's calendar day, not the server's.
                $day = $moment->setTimezone($timezone)->format('Y-m-d');
                $practiceDays[$day] = true;
            }
        }

        return new ProgressNumbers(
            words: $words,
            phrases: $phrases,
            mistakes: $mistakes,
            grammarNotes: $grammarNotes,
            practiceDays: count($practiceDays),
            currentStreak: $this->currentStreak($practiceDays, $now, $timezone),
            sessions: $sessions,
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
