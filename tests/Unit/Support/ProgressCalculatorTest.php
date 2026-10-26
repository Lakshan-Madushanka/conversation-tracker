<?php

declare(strict_types=1);

use App\Support\ProgressCalculator;
use App\Support\ProgressEntry;
use App\Support\ProgressNumbers;
use Carbon\CarbonImmutable;

function progressEntry(
    int $sessionId,
    string $finishedAt,
    int $words = 1,
    int $phrases = 0,
    int $mistakes = 0,
    int $grammarNotes = 0,
): ProgressEntry {
    return new ProgressEntry(
        sessionId: $sessionId,
        finishedAt: CarbonImmutable::parse($finishedAt),
        words: $words,
        phrases: $phrases,
        mistakes: $mistakes,
        grammarNotes: $grammarNotes,
    );
}

function calculateProgress(array $entries, string $timezone = 'UTC', ?string $now = null): ProgressNumbers
{
    return (new ProgressCalculator)->calculate(
        $entries,
        $timezone,
        CarbonImmutable::parse($now ?? '2026-10-26 12:00:00', $timezone),
    );
}

it('adds up what every finished session holds', function (): void {
    $progress = calculateProgress([
        progressEntry(1, '2026-10-26 09:00:00', words: 3, phrases: 2, mistakes: 1, grammarNotes: 4),
        progressEntry(2, '2026-10-26 10:00:00', words: 5, phrases: 1, mistakes: 3, grammarNotes: 2),
    ]);

    expect($progress->toArray())->toBe([
        'words' => 8,
        'phrases' => 3,
        'mistakes' => 4,
        'grammar_notes' => 6,
        'practice_days' => 1,
        'current_streak' => 1,
    ]);
});

it('counts a day once however many sessions were finished that day', function (): void {
    $progress = calculateProgress([
        progressEntry(1, '2026-10-26 08:00:00'),
        progressEntry(2, '2026-10-26 12:00:00'),
        progressEntry(3, '2026-10-26 20:00:00'),
    ]);

    expect($progress->practiceDays)->toBe(1)
        ->and($progress->currentStreak)->toBe(1);
});

it('counts a streak that ends today', function (): void {
    $progress = calculateProgress([
        progressEntry(1, '2026-10-24 09:00:00'),
        progressEntry(2, '2026-10-25 09:00:00'),
        progressEntry(3, '2026-10-26 09:00:00'),
    ]);

    expect($progress->practiceDays)->toBe(3)
        ->and($progress->currentStreak)->toBe(3);
});

it('keeps a streak alive when the learner has not practised today yet', function (): void {
    $progress = calculateProgress([
        progressEntry(1, '2026-10-24 09:00:00'),
        progressEntry(2, '2026-10-25 09:00:00'),
    ]);

    expect($progress->practiceDays)->toBe(2)
        ->and($progress->currentStreak)->toBe(2);
});

it('ends a streak that stopped before yesterday', function (): void {
    $progress = calculateProgress([
        progressEntry(1, '2026-10-23 09:00:00'),
        progressEntry(2, '2026-10-24 09:00:00'),
    ]);

    expect($progress->practiceDays)->toBe(2)
        ->and($progress->currentStreak)->toBe(0);
});

it('ignores a gap when a day is missing', function (): void {
    $progress = calculateProgress([
        progressEntry(1, '2026-10-22 09:00:00'),
        progressEntry(2, '2026-10-24 09:00:00'),
        progressEntry(3, '2026-10-25 09:00:00'),
        progressEntry(4, '2026-10-26 09:00:00'),
    ]);

    expect($progress->practiceDays)->toBe(4)
        ->and($progress->currentStreak)->toBe(3);
});

it('uses the learner timezone to decide the practice day', function (): void {
    // 23:30 UTC on the 25th is already the 26th in Tokyo.
    $progress = calculateProgress(
        [progressEntry(1, '2026-10-25 23:30:00')],
        'Asia/Tokyo',
        '2026-10-26 10:00:00',
    );

    expect($progress->practiceDays)->toBe(1)
        ->and($progress->currentStreak)->toBe(1);
});

it('still sees yesterday when the learner timezone is behind UTC', function (): void {
    // 01:00 UTC on the 26th is still the 25th in Los Angeles.
    $progress = calculateProgress(
        [
            progressEntry(1, '2026-10-25 18:00:00'),
            progressEntry(2, '2026-10-26 08:00:00'),
        ],
        'America/Los_Angeles',
        '2026-10-26 12:00:00',
    );

    expect($progress->practiceDays)->toBe(2)
        ->and($progress->currentStreak)->toBe(2);
});

it('keeps a streak across the daylight-saving change', function (): void {
    // Clocks move forward in New York on 8 March 2026.
    $progress = calculateProgress(
        [
            progressEntry(1, '2026-03-07 12:00:00'),
            progressEntry(2, '2026-03-08 12:00:00'),
            progressEntry(3, '2026-03-09 12:00:00'),
            progressEntry(4, '2026-03-10 12:00:00'),
        ],
        'America/New_York',
        '2026-03-10 12:00:00',
    );

    expect($progress->practiceDays)->toBe(4)
        ->and($progress->currentStreak)->toBe(4);
});

it('keeps a streak across the autumn daylight-saving change', function (): void {
    // Clocks move back in New York on 1 November 2026.
    $progress = calculateProgress(
        [
            progressEntry(1, '2026-10-31 12:00:00'),
            progressEntry(2, '2026-11-01 12:00:00'),
            progressEntry(3, '2026-11-02 12:00:00'),
        ],
        'America/New_York',
        '2026-11-02 12:00:00',
    );

    expect($progress->practiceDays)->toBe(3)
        ->and($progress->currentStreak)->toBe(3);
});

it('counts totals but no day when a finished session has no finishing moment', function (): void {
    $progress = calculateProgress([
        new ProgressEntry(
            sessionId: 1,
            finishedAt: null,
            words: 4,
            phrases: 1,
            mistakes: 2,
            grammarNotes: 3,
        ),
    ]);

    expect($progress->words)->toBe(4)
        ->and($progress->practiceDays)->toBe(0)
        ->and($progress->currentStreak)->toBe(0);
});

it('reports zero when there are no finished sessions', function (): void {
    expect(calculateProgress([])->toArray())->toBe([
        'words' => 0,
        'phrases' => 0,
        'mistakes' => 0,
        'grammar_notes' => 0,
        'practice_days' => 0,
        'current_streak' => 0,
    ]);
});
