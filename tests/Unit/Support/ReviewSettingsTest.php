<?php

declare(strict_types=1);

use App\Support\ReviewSettings;

describe('the named settings', function (): void {
    it('names one place for the size, the new-item cap and the per-kind times', function (): void {
        // About eight items, about half of them new at most, and one pace per
        // exercise kind: the numbers the daily review is tuned by, changed
        // here and nowhere else.
        expect(ReviewSettings::SIZE)->toBe(8)
            ->and(ReviewSettings::MAX_NEW_ITEMS)->toBe(4)
            ->and(ReviewSettings::FLASHCARD_SECONDS)->toBe(35)
            ->and(ReviewSettings::MISTAKE_SECONDS)->toBe(60)
            ->and(ReviewSettings::SAY_IT_AGAIN_SECONDS)->toBe(75)
            ->and(ReviewSettings::FILL_IN_THE_BLANK_SECONDS)->toBe(40);
    });

    it('keeps the target shares of the built kinds in one setting', function (): void {
        expect(ReviewSettings::TARGET_SHARES['flashcard'])->toBe(0.5)
            ->and(ReviewSettings::TARGET_SHARES['mistake'])->toBe(0.25)
            ->and(ReviewSettings::TARGET_SHARES['say_it_again'])->toBe(0.125)
            ->and(ReviewSettings::TARGET_SHARES['fill_in_the_blank'])->toBe(0.125);
    });
});
