<?php

declare(strict_types=1);

use App\Support\ReviewSettings;

describe('the named settings', function (): void {
    it('names one place for the size, the new-item cap and the flashcard time', function (): void {
        // About eight items, about half of them new at most, and 35 seconds
        // per flashcard: the numbers the daily review is tuned by, changed
        // here and nowhere else.
        expect(ReviewSettings::SIZE)->toBe(8)
            ->and(ReviewSettings::MAX_NEW_ITEMS)->toBe(4)
            ->and(ReviewSettings::FLASHCARD_SECONDS)->toBe(35);
    });
});
