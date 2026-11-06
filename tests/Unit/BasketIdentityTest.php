<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Support\BasketIdentity;
use App\Support\MistakeIdentity;

it('normalises words and phrases the way the collections name them', function (): void {
    expect(BasketIdentity::normalize(FeedbackType::Vocabulary, '  Café  '))->toBe('cafe')
        ->and(BasketIdentity::normalize(FeedbackType::Phrase, 'Break   the ICE'))->toBe('break the ice');
});

it('keeps the exact identity of the kinds that are named by an id', function (): void {
    $mistake = MistakeIdentity::pairKey('I goed', 'I went');

    expect(BasketIdentity::normalize(FeedbackType::GrammarNote, ' note-12 '))->toBe('note-12')
        ->and(BasketIdentity::normalize(FeedbackType::Mistake, $mistake))->toBe($mistake)
        ->and(BasketIdentity::normalize(FeedbackType::Question, ' 91 '))->toBe('91');
});

it('accepts a normalised identity only in the shape its kind uses', function (): void {
    expect(BasketIdentity::isValid(FeedbackType::Vocabulary, 'cafe'))->toBeTrue()
        ->and(BasketIdentity::isValid(FeedbackType::Vocabulary, ''))->toBeFalse()
        ->and(BasketIdentity::isValid(FeedbackType::GrammarNote, 'note-7'))->toBeTrue()
        ->and(BasketIdentity::isValid(FeedbackType::GrammarNote, 'note-0'))->toBeFalse()
        ->and(BasketIdentity::isValid(FeedbackType::GrammarNote, '7'))->toBeFalse()
        ->and(BasketIdentity::isValid(FeedbackType::Mistake, MistakeIdentity::pairKey('a', 'b')))->toBeTrue()
        ->and(BasketIdentity::isValid(FeedbackType::Mistake, 'mistake-nope'))->toBeFalse()
        ->and(BasketIdentity::isValid(FeedbackType::Question, '12'))->toBeTrue()
        ->and(BasketIdentity::isValid(FeedbackType::Question, '0'))->toBeFalse()
        ->and(BasketIdentity::isValid(FeedbackType::Question, 'question-12'))->toBeFalse();
});

it('reads and builds a note identity without inventing the prefix elsewhere', function (): void {
    expect(BasketIdentity::noteId('note-42'))->toBe(42)
        ->and(BasketIdentity::noteId('42'))->toBeNull()
        ->and(BasketIdentity::noteKey(42))->toBe('note-42');
});
