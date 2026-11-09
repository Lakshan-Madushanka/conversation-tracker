<?php

declare(strict_types=1);

use App\Enums\CustomReviewAttemptStatus;
use App\Models\CustomReviewAttempt;
use App\Support\CustomReviewAttemptLabel;

function stepFourAttempt(
    ?int $number,
    CustomReviewAttemptStatus $status,
): CustomReviewAttempt {
    $attempt = new CustomReviewAttempt;
    $attempt->number = $number;
    $attempt->status = $status;

    return $attempt;
}

it('names the latest attempt by its number, or says there is none yet', function (): void {
    expect(CustomReviewAttemptLabel::number(null))->toBe(CustomReviewAttemptLabel::NONE)
        ->and(CustomReviewAttemptLabel::number(stepFourAttempt(null, CustomReviewAttemptStatus::NotStarted)))
        ->toBe(CustomReviewAttemptLabel::NONE)
        ->and(CustomReviewAttemptLabel::number(stepFourAttempt(3, CustomReviewAttemptStatus::Completed)))
        ->toBe('3rd attempt');
});

it('names a numbered attempt the same way everywhere, or says it never began', function (): void {
    expect(CustomReviewAttemptLabel::numberLabel(null))->toBe('Not started')
        ->and(CustomReviewAttemptLabel::numberLabel(1))->toBe('1st attempt')
        ->and(CustomReviewAttemptLabel::numberLabel(2))->toBe('2nd attempt')
        ->and(CustomReviewAttemptLabel::numberLabel(3))->toBe('3rd attempt')
        ->and(CustomReviewAttemptLabel::numberLabel(11))->toBe('11th attempt')
        ->and(CustomReviewAttemptLabel::numberLabel(12))->toBe('12th attempt');
});

it('names the attempt before the latest by its status', function (): void {
    expect(CustomReviewAttemptLabel::previous(null))->toBeNull()
        ->and(CustomReviewAttemptLabel::previous(CustomReviewAttemptStatus::Completed))
        ->toBe('Previous attempt: Completed')
        ->and(CustomReviewAttemptLabel::previous(CustomReviewAttemptStatus::FinishedEarly))
        ->toBe('Previous attempt: Finished early');
});

it("words the quiet heading above an attempt's progress", function (): void {
    expect(CustomReviewAttemptLabel::heading('Morning set', stepFourAttempt(1, CustomReviewAttemptStatus::InProgress)))
        ->toBe('Morning set · 1st attempt')
        ->and(CustomReviewAttemptLabel::heading('Morning set', stepFourAttempt(2, CustomReviewAttemptStatus::InProgress)))
        ->toBe('Morning set · 2nd attempt')
        ->and(CustomReviewAttemptLabel::heading('Morning set', stepFourAttempt(11, CustomReviewAttemptStatus::InProgress)))
        ->toBe('Morning set · 11th attempt')
        ->and(CustomReviewAttemptLabel::heading('Morning set', stepFourAttempt(12, CustomReviewAttemptStatus::InProgress)))
        ->toBe('Morning set · 12th attempt')
        ->and(CustomReviewAttemptLabel::heading('Morning set', stepFourAttempt(null, CustomReviewAttemptStatus::NotStarted)))
        ->toBe('Morning set · Not started');
});

it('summarises how many attempts there were', function (): void {
    expect(CustomReviewAttemptLabel::summary(0, null))->toBe(CustomReviewAttemptLabel::NONE)
        ->and(CustomReviewAttemptLabel::summary(
            1,
            stepFourAttempt(1, CustomReviewAttemptStatus::InProgress),
        ))->toBe(CustomReviewAttemptLabel::IN_PROGRESS)
        ->and(CustomReviewAttemptLabel::summary(
            1,
            stepFourAttempt(1, CustomReviewAttemptStatus::Completed),
        ))->toBe('1 attempt');
});
