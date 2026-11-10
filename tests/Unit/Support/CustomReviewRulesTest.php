<?php

declare(strict_types=1);

use App\Support\CustomReviewRules;

it('treats the recommended size as advice, never a limit', function (): void {
    expect(CustomReviewRules::isTooMany(20))->toBeFalse()
        ->and(CustomReviewRules::isTooMany(21))->toBeTrue()
        ->and(CustomReviewRules::needsConfirmation(21, 1))->toBeTrue()
        ->and(CustomReviewRules::needsConfirmation(21, 0))->toBeFalse()
        ->and(CustomReviewRules::needsConfirmation(20, 1))->toBeFalse();
});

it('refuses only an add that ends past the maximum', function (): void {
    expect(CustomReviewRules::crossesMaximum(49))->toBeFalse()
        ->and(CustomReviewRules::crossesMaximum(50))->toBeFalse()
        ->and(CustomReviewRules::crossesMaximum(51))->toBeTrue();
});

it('names the room left, down to the last item and the full review', function (): void {
    expect(CustomReviewRules::maximumMessage(0))->toBe('This review has room for 50 more items.')
        ->and(CustomReviewRules::maximumMessage(45))->toBe('This review has room for 5 more items.')
        ->and(CustomReviewRules::maximumMessage(49))->toBe('This review has room for 1 more item.')
        ->and(CustomReviewRules::maximumMessage(50))->toBe('This review is full at 50 items.');
});
