<?php

declare(strict_types=1);

use App\Support\ReviewAllocation;
use App\Support\ReviewSettings;

describe('splitting a sitting between the available kinds', function (): void {
    it('renormalises the shares over the kinds that have something to offer', function (): void {
        // Flashcards and mistakes take part; the missing kinds never take a
        // slot. Their shares still add up to the whole sitting.
        $quotas = ReviewAllocation::quotas(
            ['flashcard', 'mistake'],
            ReviewSettings::SIZE,
            ReviewSettings::TARGET_SHARES,
        );

        expect($quotas)->toBe(['flashcard' => 5, 'mistake' => 3]);
    });

    it('shares the sitting between all three built kinds', function (): void {
        $quotas = ReviewAllocation::quotas(
            ['flashcard', 'mistake', 'say_it_again'],
            ReviewSettings::SIZE,
            ReviewSettings::TARGET_SHARES,
        );

        expect($quotas)->toBe([
            'flashcard' => 5,
            'mistake' => 2,
            'say_it_again' => 1,
        ]);
    });

    it('gives the whole sitting to the only kind with items', function (): void {
        expect(ReviewAllocation::quotas(['say_it_again'], 8, ReviewSettings::TARGET_SHARES))
            ->toBe(['say_it_again' => 8]);
    });

    it('has nothing to share when no kind has items', function (): void {
        expect(ReviewAllocation::quotas([], 8, ReviewSettings::TARGET_SHARES))->toBe([]);
    });

    it('asks for no items when the sitting is empty', function (): void {
        expect(ReviewAllocation::quotas(
            ['flashcard', 'mistake'],
            0,
            ReviewSettings::TARGET_SHARES,
        ))->toBe(['flashcard' => 0, 'mistake' => 0]);
    });

    it('gives every available kind at least one slot when there is room', function (): void {
        // The shares would leave the question step out entirely on a tiny
        // sitting, yet every kind with items should still earn its step.
        $quotas = ReviewAllocation::quotas(
            ['flashcard', 'mistake', 'say_it_again'],
            3,
            ['flashcard' => 0.9, 'mistake' => 0.09, 'say_it_again' => 0.01],
        );

        expect($quotas)->toBe([
            'flashcard' => 1,
            'mistake' => 1,
            'say_it_again' => 1,
        ]);
    });

    it('does not force a kind in when the sitting cannot hold them all', function (): void {
        $quotas = ReviewAllocation::quotas(
            ['flashcard', 'mistake', 'say_it_again'],
            2,
            ReviewSettings::TARGET_SHARES,
        );

        expect(array_sum($quotas))->toBe(2)
            ->and($quotas['flashcard'])->toBeGreaterThanOrEqual(1);
    });

    it('keeps the total equal to the sitting size for any number of items', function (): void {
        foreach (range(1, 12) as $size) {
            $quotas = ReviewAllocation::quotas(
                ['flashcard', 'mistake', 'say_it_again'],
                $size,
                ReviewSettings::TARGET_SHARES,
            );

            expect(array_sum($quotas))->toBe($size);
        }
    });

    it('falls back to a full share for a kind the settings do not name', function (): void {
        $quotas = ReviewAllocation::quotas(
            ['flashcard', 'fill_in_the_blank'],
            6,
            ['flashcard' => 0.5],
        );

        // The unnamed kind is treated as a share of 1.0 beside the named 0.5.
        expect($quotas)->toBe(['flashcard' => 2, 'fill_in_the_blank' => 4]);
    });

    it('counts a kind once even when it is named twice', function (): void {
        expect(ReviewAllocation::quotas(
            ['flashcard', 'flashcard', 'mistake'],
            4,
            ReviewSettings::TARGET_SHARES,
        ))->toBe(['flashcard' => 3, 'mistake' => 1]);
    });
});
