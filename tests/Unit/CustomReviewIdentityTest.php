<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Support\CustomReviewIdentity;
use App\Support\MistakeIdentity;

it('normalises words and phrases the way the collections name them', function (): void {
    expect(CustomReviewIdentity::normalize(FeedbackType::Vocabulary, '  Café  '))->toBe('cafe')
        ->and(CustomReviewIdentity::normalize(FeedbackType::Phrase, 'Break   the ICE'))->toBe('break the ice');
});

it('keeps the exact identity of the kinds that are named by an id', function (): void {
    $mistake = MistakeIdentity::pairKey('I goed', 'I went');

    expect(CustomReviewIdentity::normalize(FeedbackType::GrammarNote, ' note-12 '))->toBe('note-12')
        ->and(CustomReviewIdentity::normalize(FeedbackType::Mistake, $mistake))->toBe($mistake)
        ->and(CustomReviewIdentity::normalize(FeedbackType::Question, ' 91 '))->toBe('91');
});

it('accepts a normalised identity only in the shape its kind uses', function (): void {
    expect(CustomReviewIdentity::isValid(FeedbackType::Vocabulary, 'cafe'))->toBeTrue()
        ->and(CustomReviewIdentity::isValid(FeedbackType::Vocabulary, ''))->toBeFalse()
        ->and(CustomReviewIdentity::isValid(FeedbackType::GrammarNote, 'note-7'))->toBeTrue()
        ->and(CustomReviewIdentity::isValid(FeedbackType::GrammarNote, 'note-0'))->toBeFalse()
        ->and(CustomReviewIdentity::isValid(FeedbackType::GrammarNote, '7'))->toBeFalse()
        ->and(CustomReviewIdentity::isValid(FeedbackType::Mistake, MistakeIdentity::pairKey('a', 'b')))->toBeTrue()
        ->and(CustomReviewIdentity::isValid(FeedbackType::Mistake, 'mistake-nope'))->toBeFalse()
        ->and(CustomReviewIdentity::isValid(FeedbackType::Question, '12'))->toBeTrue()
        ->and(CustomReviewIdentity::isValid(FeedbackType::Question, '0'))->toBeFalse()
        ->and(CustomReviewIdentity::isValid(FeedbackType::Question, 'question-12'))->toBeFalse()
        ->and(CustomReviewIdentity::isValid(FeedbackType::NaturalVersion, 'anything'))->toBeFalse();
});

it('refuses an identity longer than the column that stores it', function (): void {
    expect(CustomReviewIdentity::isValid(FeedbackType::Vocabulary, str_repeat('a', 191)))->toBeTrue()
        ->and(CustomReviewIdentity::isValid(FeedbackType::Vocabulary, str_repeat('a', 192)))->toBeFalse();
});

it('reads and builds a note identity without inventing the prefix elsewhere', function (): void {
    expect(CustomReviewIdentity::noteId('note-42'))->toBe(42)
        ->and(CustomReviewIdentity::noteId('42'))->toBeNull()
        ->and(CustomReviewIdentity::noteKey(42))->toBe('note-42');
});
