<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The numbers progress shows: what the learner has collected and how often
 * they have practised. Both the totals and the days are worked out from the
 * finished sessions that still exist, including archived ones.
 */
final readonly class ProgressNumbers
{
    public function __construct(
        public int $words = 0,
        public int $phrases = 0,
        public int $mistakes = 0,
        public int $grammarNotes = 0,
        public int $practiceDays = 0,
        public int $currentStreak = 0,
    ) {}

    /**
     * @return array{
     *     words: int,
     *     phrases: int,
     *     mistakes: int,
     *     grammar_notes: int,
     *     practice_days: int,
     *     current_streak: int
     * }
     */
    public function toArray(): array
    {
        return [
            'words' => $this->words,
            'phrases' => $this->phrases,
            'mistakes' => $this->mistakes,
            'grammar_notes' => $this->grammarNotes,
            'practice_days' => $this->practiceDays,
            'current_streak' => $this->currentStreak,
        ];
    }
}
