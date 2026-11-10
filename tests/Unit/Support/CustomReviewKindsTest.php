<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Support\CustomReviewKinds;

it('says "All kinds" when a review allows every kind', function (): void {
    expect(CustomReviewKinds::label(null))->toBe('All kinds')
        ->and(CustomReviewKinds::label([]))->toBe('All kinds')
        ->and(CustomReviewKinds::label(FeedbackType::customReviewValues()))->toBe('All kinds');
});

it('names a chosen subset in the collections\' words', function (): void {
    expect(CustomReviewKinds::label(['vocabulary']))->toBe('Words only')
        ->and(CustomReviewKinds::label(['phrase']))->toBe('Phrases only')
        ->and(CustomReviewKinds::label(['question']))->toBe('Questions only')
        ->and(CustomReviewKinds::label(['vocabulary', 'phrase']))->toBe('Words + Phrases')
        ->and(CustomReviewKinds::label(['grammar_note', 'mistake']))->toBe('Grammar notes + Mistakes');
});

it('keeps the chosen kinds in the order the review groups them, whatever order they arrive in', function (): void {
    expect(CustomReviewKinds::label(['phrase', 'vocabulary']))->toBe('Words + Phrases')
        ->and(CustomReviewKinds::label(['question', 'mistake', 'vocabulary']))->toBe('Words + Mistakes + Questions');
});

it('refuses a disallowed item with the kinds the review does allow', function (): void {
    expect(CustomReviewKinds::addRefusalMessage(['vocabulary']))
        ->toBe('This review allows Words only.')
        ->and(CustomReviewKinds::addRefusalMessage(['vocabulary', 'phrase']))
        ->toBe('This review allows Words + Phrases only.')
        ->and(CustomReviewKinds::addRefusalMessage(['question']))
        ->toBe('This review allows Questions only.');
});

it('names the held items in the way of turning a kind off', function (): void {
    expect(CustomReviewKinds::narrowingMessage(FeedbackType::Phrase, 4))
        ->toBe('Remove the 4 phrase items first.')
        ->and(CustomReviewKinds::narrowingMessage(FeedbackType::Vocabulary, 1))
        ->toBe('Remove the 1 word item first.')
        ->and(CustomReviewKinds::narrowingMessage(FeedbackType::GrammarNote, 2))
        ->toBe('Remove the 2 grammar note items first.');
});
